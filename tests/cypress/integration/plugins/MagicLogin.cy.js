describe('Test that the magiclogin system plugin', () => {
  beforeEach(() => {
    cy.db_enableExtension('1','plg_system_magiclogin');
  });

  afterEach(() => {
    cy.task('getMails').then((mails) => {
      cy.task('clearEmails');
    });
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
        cy.wrap(mails[0].body).should('have.string', 'Click the link below to login');
        cy.wrap(mails[0].headers.subject).should('have.string', 'Login to Joomla CMS Test');
        cy.wrap(mails[0].headers.from).should('equal', `"${Cypress.expose('sitename')}" <${Cypress.expose('email')}>`);
        cy.wrap(mails[0].headers.to).should('equal', 'magic@example.com');
    });
  });
/*
  it('can login using magic link from email', () => {
    cy.db_createUser({ name: 'Magic User', username: 'magicuser', email: 'magic@example.com', password: '098f6bcd4621d373cade4e832627b4f6' });
    
    cy.visit('/index.php?option=com_users&view=login');
    cy.get('#username').type('magic@example.com');
    cy.get('#password').type('1');
    cy.get('.controls > button[type="submit"].btn').click();
    
    cy.task('getMails').then((mails) => {
      expect(mails).to.have.length(1);
      
      const htmlContent = mails[0].html;
      const magicLinkMatch = htmlContent.match(/href=['"]([^'"]*magic_token=[^'"]*)['"]/);
      expect(magicLinkMatch).to.not.be.null;
      
      const magicLink = magicLinkMatch[1];
      const url = new URL(magicLink);
      const token = url.searchParams.get('magic_token');
      
      expect(token).to.have.length.greaterThan(0);
      
      cy.visit(`/?magic_token=${token}`);
      cy.contains('You have been successfully logged in').should('be.visible');
      
      cy.visit('/index.php?option=com_users&view=login');
      cy.get('.com-users-logout').should('contain.text', 'Log out');
    });
  });

  it('rejects invalid magic token', () => {
    cy.visit('/?magic_token=invalidtoken123456789');
    cy.contains('This magic link is invalid or has expired').should('be.visible');
  });

  it('rejects expired magic token', () => {
    cy.db_createUser({ name: 'Magic User', username: 'magicuser', email: 'magic@example.com', password: '098f6bcd4621d373cade4e832627b4f6' });
    
    cy.visit('/index.php?option=com_users&view=login');
    cy.get('#username').type('magic@example.com');
    cy.get('.controls > button[type="submit"].btn').click();
    
    cy.task('getMails').then((mails) => {
      const htmlContent = mails[0].html;
      const magicLinkMatch = htmlContent.match(/href=['"]([^'"]*magic_token=[^'"]*)['"]/);
      const magicLink = magicLinkMatch[1];
      const url = new URL(magicLink);
      const token = url.searchParams.get('magic_token');
      
      cy.task('queryDB', 'UPDATE #__magiclogin_tokens SET expires = DATE_SUB(NOW(), INTERVAL 1 HOUR)');
      
      cy.visit(`/?magic_token=${token}`);
      cy.contains('This magic link is invalid or has expired').should('be.visible');
    });
  });

  it('does not reveal if email does not exist', () => {
    cy.visit('/index.php?option=com_users&view=login');
    cy.get('#username').type('nonexistent@example.com');
    cy.get('.controls > button[type="submit"].btn').click();
    
    cy.contains('If this email address is registered').should('be.visible');
    
    cy.task('getMails').then((mails) => {
      expect(mails).to.have.length(0);
    });
  });

  it('does not send magic link to blocked users', () => {
    cy.db_createUser({ name: 'Blocked User', username: 'blockeduser', email: 'blocked@example.com', password: '098f6bcd4621d373cade4e832627b4f6', block: 1 });
    
    cy.visit('/index.php?option=com_users&view=login');
    cy.get('#username').type('blocked@example.com');
    cy.get('.controls > button[type="submit"].btn').click();
    
    cy.contains('If this email address is registered').should('be.visible');
    
    cy.task('getMails').then((mails) => {
      expect(mails).to.have.length(0);
    });
  });

  it('enforces rate limiting', () => {
    cy.db_createUser({ name: 'Magic User', username: 'magicuser', email: 'magic@example.com', password: '098f6bcd4621d373cade4e832627b4f6' });
    
    for (let i = 0; i < 4; i++) {
      cy.visit('/index.php?option=com_users&view=login');
      cy.get('#username').type('magic@example.com');
      cy.get('.controls > button[type="submit"].btn').click();
      cy.wait(500);
    }
    
    cy.task('getMails').then((mails) => {
      expect(mails.length).to.be.lessThan(4);
    });
  });

  it('token can only be used once', () => {
    cy.db_createUser({ name: 'Magic User', username: 'magicuser', email: 'magic@example.com', password: '098f6bcd4621d373cade4e832627b4f6' });
    
    cy.visit('/index.php?option=com_users&view=login');
    cy.get('#username').type('magic@example.com');
    cy.get('.controls > button[type="submit"].btn').click();
    
    cy.task('getMails').then((mails) => {
      const htmlContent = mails[0].html;
      const magicLinkMatch = htmlContent.match(/href=['"]([^'"]*magic_token=[^'"]*)['"]/);
      const magicLink = magicLinkMatch[1];
      const url = new URL(magicLink);
      const token = url.searchParams.get('magic_token');
      
      cy.visit(`/?magic_token=${token}`);
      cy.contains('You have been successfully logged in').should('be.visible');
      
      cy.doFrontendLogout();
      
      cy.visit(`/?magic_token=${token}`);
      cy.contains('This magic link is invalid or has expired').should('be.visible');
    });
  });

  it('validates IP address and user agent', () => {
    cy.db_createUser({ name: 'Magic User', username: 'magicuser', email: 'magic@example.com', password: '098f6bcd4621d373cade4e832627b4f6' });
    
    cy.visit('/index.php?option=com_users&view=login');
    cy.get('#username').type('magic@example.com');
    cy.get('.controls > button[type="submit"].btn').click();
    
    cy.task('getMails').then((mails) => {
      const htmlContent = mails[0].html;
      const magicLinkMatch = htmlContent.match(/href=['"]([^'"]*magic_token=[^'"]*)['"]/);
      const magicLink = magicLinkMatch[1];
      const url = new URL(magicLink);
      const token = url.searchParams.get('magic_token');
      
      cy.task('queryDB', 'UPDATE #__magiclogin_tokens SET ip_address = "999.999.999.999"');
      
      cy.visit(`/?magic_token=${token}`);
      cy.contains('This magic link is invalid or has expired').should('be.visible');
    });
  });
  */
});
