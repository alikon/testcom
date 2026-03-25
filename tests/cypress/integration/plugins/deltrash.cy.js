describe('Joomla Task Plugin: Deltrash Test', () => {
  const adminUrl = '/administrator';
  const username = 'admin'; // Use env variables in production
  const password = 'password';

  beforeEach(() => {
    // 1. Login to the Joomla Backend
    cy.visit(adminUrl);
    cy.get('#mod-login-username').type(username);
    cy.get('#mod-login-password').type(password);
    cy.get('.btn-primary.w-100').click();
    
    // Ensure we are logged in
    cy.location('pathname').should('include', '/administrator/index.php');
  });

  it('should empty the trash using the deltrash task', () => {
    // 2. Create dummy content and move it to trash
    // (Or navigate to Articles and trash an existing one)
    cy.visit(`${adminUrl}/index.php?option=com_content&view=articles`);
    cy.get('#cb0').click(); // Select the first article
    cy.get('.button-trash').click(); // Move to trash
    
    // 3. Navigate to Scheduled Tasks
    cy.visit(`${adminUrl}/index.php?option=com_scheduler&view=tasks`);

    // 4. Find the 'deltrash' task and run it
    // Note: This assumes you have already created a task instance for this plugin
    cy.contains('deltrash').parents('tr').find('.js-scheduler-run-task').click();

    // 5. Verify the success message
    cy.get('.alert-message').should('contain', 'Task successfully executed');

    // 6. Final check: Go to the Trash view and ensure it is empty
    cy.visit(`${adminUrl}/index.php?option=com_content&view=articles&filter[published]=-2`);
    cy.get('.no-results-message').should('exist');
  });
});
