const submitContactForm = () => {
  cy.get('#jform_contact_name').type('Test User');
  cy.get('#jform_contact_email').type('testuser@example.com');
  cy.get('#jform_contact_emailmsg').type('Test Subject');
  cy.get('#jform_contact_message').type('Test message content');
  cy.get('button.btn.btn-primary.validate[type="submit"]').click();
  //cy.get('.alert-success').should('be.visible');
};

describe('Test in frontend that the contact form view', () => {
  afterEach(() => {
    cy.task('queryDB', 'DELETE FROM #__contact_details');
    cy.db_updateExtensionParameter('custom_reply', '0', 'com_contact');
    cy.db_enableExtension('0', 'plg_contact_customreply');
  });

  it('can create a contact through a form', () => {
    cy.doFrontendLogin();
    cy.visit('/index.php?option=com_contact&view=form&layout=edit');
    cy.get('#jform_name').type('test contact 1');
    cy.get('.mb-2 > .btn-primary').click();

    cy.task('queryDB', 'SELECT catid FROM #__contact_details WHERE name = \'test contact 1\'').then((id) => {
      cy.visit(`/index.php?option=com_contact&view=category&id=${id[0].catid}`);

      cy.contains('test contact 1').should('exist');
    });
  });

  it('can send an email on contact form submission', () => {
    cy.task('clearEmails');
    cy.db_getUserId().then((id) => cy.db_createContact({ name: 'test contact', user_id: id }))
      .then((contact) => {
        cy.visit(`/index.php?option=com_contact&view=contact&id=${contact.id}`);
        submitContactForm();

        cy.task('getMails').then((mails) => {
          expect(mails).to.have.length(1);
          const mail = mails[0];
          if (contact.email_to) {
            expect(mail.to).to.contain(contact.email_to);
          }
          expect(mail.subject).to.contain('Test Subject');
          expect(mail.body).to.contain('Test message content');
        });
      });
  });

  it('can send an email on contact form submission with custom reply enabled', () => {
    cy.task('clearEmails');
    cy.db_updateExtensionParameter('custom_reply', '1', 'com_contact');
    cy.db_enableExtension('1', 'plg_contact_customreply');
    cy.db_getUserId().then((id) => cy.db_createContact({ name: 'test contact', user_id: id }))
      .then((contact) => {
        cy.visit(`/index.php?option=com_contact&view=contact&id=${contact.id}`);
        submitContactForm();

        cy.task('getMails').then((mails) => {
          expect(mails).to.have.length(2);
          const toAddresses = mails.map((m) => m.to).join(' ');
          expect(toAddresses).to.contain('testuser@example.com');
          mails.forEach((mail) => expect(mail.subject).to.contain('Test Subject'));
          mails.forEach((mail) => expect(mail.body).to.contain('Test message content'));
        });
      });
  });
});
