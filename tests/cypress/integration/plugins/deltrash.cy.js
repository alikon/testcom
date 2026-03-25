describe('Joomla Task Plugin: Deltrash Test', () => {

  beforeEach(() => {
    // 1. Login to the Joomla Backend
    cy.doAdministratorLogin();
    cy.db_enableExtension('1', 'plg_task_deltrash');
  });

  it('can display notification form', () => {
    cy.visit('/administrator/index.php?option=com_scheduler&view=tasks');
    cy.clickToolbarButton('New');
    cy.get('div.new-task-details').contains('Delete trashed items').click();
    cy.title().should('contain', 'New Task');
    cy.get('h1.page-title').should('contain', 'New Task');
    cy.get('#myTab div[role="tablist"] button[aria-controls="advanced"]').click();
    cy.get('#task-form').contains('Delete trashed items').should('be.visible');
  });

  it('can notify successful task execution', () => {
    cy.db_createSchedulerTask({
      title: 'Test task',
      type: 'plg_task_deltrash',
      execution_rules: { 'rule-type': 'manual' },
      cron_rules: { type: 'manual', exp: '' },
      params: {
         notifications: { success_mail: 1 },
         articles:1,
         categories:1,
         components:["com_banners"],
         contacts:0,
         menus:0,
         modules:0,
         redirects:0,
         redirectspurge":0,
         tags:0,
         tasks:0
      },
    }).then((task) => {
      cy.visit('/administrator/index.php?option=com_scheduler&view=tasks&filter=');
      cy.searchForItem('Test task');
      cy.intercept('GET', '**/administrator/index.php?option=com_ajax&format=json&plugin=RunSchedulerTest&group=system&id=*').as('runschedulertest');
      cy.get('button[data-scheduler-run]').should('have.attr', 'data-id', task.id).click();
      cy.wait('@runschedulertest').then((interception) => {
        expect(interception.response.body.message).to.eq(null);
        expect(interception.response.body.success).to.eq(true);
      });
      cy.get('joomla-dialog[type="inline"]').should('be.visible');
      cy.get('joomla-dialog[type="inline"]').within(() => {
        cy.get('header.joomla-dialog-header').should('contain', `Test task (ID: ${task.id})`);
        cy.get('div.scheduler-status').should('contain', 'Status: Completed');
      });
      cy.task('getMails').then((mails) => {
        cy.wrap(mails).should('have.lengthOf', 1);
        cy.wrap(mails[0].body).should('have.string', `Scheduled Task#${task.id}, Test task, has been successfully executed`);
        cy.wrap(mails[0].headers.subject).should('have.string', 'Task Successful');
        cy.wrap(mails[0].headers.from).should('equal', `"${Cypress.env('sitename')}" <${Cypress.env('email')}>`);
        cy.wrap(mails[0].headers.to).should('equal', Cypress.env('email'));
      });
    });
  });
  
  it('should empty the trash using the deltrash task', () => {
    // 2. Create dummy content and move it to trash
    trashDummyArticle();// Move to trash
    
    // 3. Navigate to Scheduled Tasks
    cy.visit('/administrator/index.php?option=com_scheduler&view=tasks');

    // 4. Find the 'deltrash' task and run it
    // Note: This assumes you have already created a task instance for this plugin
    cy.contains('deltrash').parents('tr').find('.js-scheduler-run-task').click();

    // 5. Verify the success message
    cy.get('.alert-message').should('contain', 'Task successfully executed');

    // 6. Final check: Go to the Trash view and ensure it is empty
    cy.visit('/administrator/index.php?option=com_content&view=articles&filter=[published]=-2');
    cy.get('.no-results-message').should('exist');
  });

  // Helper function to ensure we have a trashed item
  function trashDummyArticle() {
    cy.db_createArticle({ title: 'Test trash article' }).then(() => {
      cy.reload();
      cy.searchForItem('Test trash article');
      cy.checkAllResults();
      cy.clickToolbarButton('Action');
      cy.contains('Trash').click();

      cy.checkForSystemMessage('Article trashed.');
    });
  }
});
