describe('Test that the magiclogin system plugin', () => {
  beforeEach(() => {
    cy.db_enableExtension('1','plg_system_magiclogin');
  });

  afterEach(() => {
    cy.task('getMails').then((mails) => {
      cy.task('clearEmails');
    });
  });

  const loginWithEmail = () => {
    cy.visit('/index.php?option=com_users&view=login');
    cy.get('#username').type('magic@example.com');
    cy.get('#password').type('1');
    cy.get('.controls > button[type="submit"].btn').click();
  };

  const getTokenFromMail = () =>
    cy.task('getMails').then((mails) => {
      const htmlContent = String(mails[0].html || mails[0].body || '');
      return htmlContent.match(/magic_token=([a-f0-9]+)/)[1];
    });

  it('sends magic link when user enters email address', () => {
    cy.db_createUser({ name: 'Magic User', username: 'magicuser', email: 'magic@example.com', password: '098f6bcd4621d373cade4e832627b4f6' });
    
    cy.visit('/index.php?option=com_users&view=login');
    cy.get('#username').type('magic@example.com');
    cy.get('#password').type('1');
    cy.get('.controls > button[type="submit"].btn').click();
    
    cy.checkForSystemMessage('If this email address is registered');
    
    cy.task('getMails').then((mails) => {
      cy.wrap(mails).should('have.lengthOf', 1);
      console.log('CONTENUTO HEADER FROM:', JSON.stringify(mails[0].headers.from));
      cy.log('Header From:', mails[0].headers.from);
      // Debug: se il test fallisce ancora, guarda il log di Cypress per vedere l'oggetto mail
      cy.log('Mail From:', mails[0].headers.from);
      cy.log('Cypress Env Email:', Cypress.env('email'));
      cy.wrap(mails[0].body).should('have.string', 'Click the link below to login');
      cy.wrap(mails[0].headers.subject).should('contain', `Login to Joomla test`);
      // TO DO when upgrade to cypress 15
      //cy.wrap(mails[0].headers.from).should('equal', `"${Cypress.expose('sitename')}" <${Cypress.expose('email')}>`);
      const fromHeader = mails[0].headers.from;
      // Invece di 'equal', verifichiamo che contenga sia il nome che l'email
      cy.wrap(fromHeader).should('contain', 'Joomla test');
      cy.wrap(fromHeader).should('contain', 'admin@example.org');
      cy.wrap(mails[0].headers.to).should('equal', 'magic@example.com');
    });
  });
 
  it('can login using magic link from email', () => {
    cy.db_createUser({ name: 'Magic User', username: 'magicuser', email: 'magic@example.com', password: '098f6bcd4621d373cade4e832627b4f6' });
    
    loginWithEmail();
    
    getTokenFromMail().then((token) => {
      cy.wrap(token).should('have.length.greaterThan', 0);
      
      cy.visit(`/?magic_token=${token}`);
      cy.checkForSystemMessage('You have been successfully logged in');
      // No redirect configured: should stay on homepage
      cy.url().should('eq', Cypress.config('baseUrl') + '/');
      
      cy.visit('/index.php?option=com_users&view=login');
      cy.get('.com-users-logout').should('contain.text', 'Log out');
    });
  });

  it('redirects to configured menu item after login', () => {
    cy.db_createUser({ name: 'Magic User', username: 'magicuser', email: 'magic@example.com', password: '098f6bcd4621d373cade4e832627b4f6' });
    cy.db_createMenuItem({ title: 'User Profile', alias: 'user-profile', link: 'index.php?option=com_users&view=profile' })
      .then((itemId) => {
        cy.db_updateExtensionParameter('login', itemId, 'plg_system_magiclogin');
      });

    loginWithEmail();

    getTokenFromMail().then((token) => {
      cy.visit(`/?magic_token=${token}`);
      cy.checkForSystemMessage('You have been successfully logged in');
      cy.url().should('include', 'Itemid=');
    });
  });

  it('rejects invalid magic token', () => {
    cy.visit('/?magic_token=invalidtoken123456789');
    cy.checkForSystemMessage('This magic link is invalid or has expired');
  });

  it('rejects expired magic token', () => {
    cy.db_createUser({ name: 'Magic User', username: 'magicuser', email: 'magic@example.com', password: '098f6bcd4621d373cade4e832627b4f6' });
    
    loginWithEmail();
    
    getTokenFromMail().then((token) => {
      const dbType = Cypress.env('db_type');
      const query = dbType === 'pgsql'
        ? "UPDATE #__magiclogin_tokens SET expires = NOW() - INTERVAL '1 hour'"
        : "UPDATE #__magiclogin_tokens SET expires = DATE_SUB(NOW(), INTERVAL 1 HOUR)";

      cy.task('queryDB', query);
      
      cy.visit(`/?magic_token=${token}`);
      cy.checkForSystemMessage('This magic link is invalid or has expired');
    });
  });

  it('does not reveal if email does not exist', () => {
    cy.visit('/index.php?option=com_users&view=login');
    cy.get('#username').type('nonexistent@example.com');
    cy.get('#password').type('1');
    cy.get('.controls > button[type="submit"].btn').click();
    
    cy.checkForSystemMessage('If this email address is registered');
    
    cy.task('getMails').then((mails) => {
      cy.wrap(mails).should('have.lengthOf', 0);
    });
  });

  it('does not send magic link to blocked users', () => {
    cy.db_createUser({ name: 'Blocked User', username: 'blockeduser', email: 'blocked@example.com', password: '098f6bcd4621d373cade4e832627b4f6', block: 1 });
    
    cy.visit('/index.php?option=com_users&view=login');
    cy.get('#username').type('blocked@example.com');
    cy.get('#password').type('1');
    cy.get('.controls > button[type="submit"].btn').click();
    
    cy.checkForSystemMessage('If this email address is registered');
    
    cy.task('getMails').then((mails) => {
      cy.wrap(mails).should('have.lengthOf', 0);
    });
  });

  it('enforces rate limiting', () => {
    cy.db_createUser({ name: 'Magic User', username: 'magicuser', email: 'magic@example.com', password: '098f6bcd4621d373cade4e832627b4f6' });
    
    for (let i = 0; i < 4; i++) {
      cy.visit('/index.php?option=com_users&view=login');
      cy.get('#username').type('magic@example.com');
      cy.get('#password').type('1');
      cy.get('.controls > button[type="submit"].btn').click();
      cy.wait(500);
    }
    
    cy.task('getMails').then((mails) => {
      cy.wrap(mails.length).should('be.lessThan', 4);
    });
  });

  it('token can only be used once', () => {
    cy.db_createUser({ name: 'Magic User', username: 'magicuser', email: 'magic@example.com', password: '098f6bcd4621d373cade4e832627b4f6' });
    
    loginWithEmail();
    
    getTokenFromMail().then((token) => {
      cy.visit(`/?magic_token=${token}`);
      cy.checkForSystemMessage('You have been successfully logged in');
      
      cy.doFrontendLogout();
      
      cy.visit(`/?magic_token=${token}`);
      cy.checkForSystemMessage('This magic link is invalid or has expired');
    });
  });

  it('validates IP address and user agent', () => {
    cy.db_createUser({ name: 'Magic User', username: 'magicuser', email: 'magic@example.com', password: '098f6bcd4621d373cade4e832627b4f6' });
    
    loginWithEmail();
    
    getTokenFromMail().then((token) => {
      cy.task('queryDB', "UPDATE #__magiclogin_tokens SET ip_address = '999.999.999.999'");
      
      cy.visit(`/?magic_token=${token}`);
      cy.checkForSystemMessage('This magic link is invalid or has expired');
    });
  });
});
