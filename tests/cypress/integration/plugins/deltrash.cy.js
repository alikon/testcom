describe('Joomla Task Plugin: Deltrash Test', () => {

  beforeEach(() => {
    // 1. Login to the Joomla Backend
    cy.task('clearEmails');
    cy.doAdministratorLogin();
    cy.db_enableExtension('1', 'plg_task_deltrash');
  });

  it('can display the plugin form', () => {
    cy.visit('/administrator/index.php?option=com_scheduler&view=tasks');
    cy.clickToolbarButton('New');
    cy.get('div.new-task-details').contains('Delete trashed items').click();
    cy.title().should('contain', 'New Task');
    cy.get('h1.page-title').should('contain', 'New Task');
    // Verify the plugin header is visible on the General tab
    cy.get('#general').contains('Delete trashed items').should('be.visible')
  });

  it('should empty the trash using the deltrash task', () => {
    // Create dummy content and move it to trash
    cy.db_createArticle({ title: 'Test trash article' }).then(() => {
      cy.reload();
      cy.visit('/administrator/index.php?option=com_content&view=articles&filter=');
      cy.searchForItem('Test trash article');
      cy.checkAllResults();
      cy.clickToolbarButton('Action');
      cy.contains('Trash').click();

      cy.checkForSystemMessage('Article trashed.');
    });
    
    cy.db_createSchedulerTask({
      title: 'Test task',
      type: 'plg_task_deltrash',
      execution_rules: { 'rule-type': 'manual' },
      cron_rules: { type: 'manual', exp: '' },
      params: {
         notifications: { success_mail: 0 },
         articles:1,
         categories:1,
         components:["com_banners"],
         contacts:0,
         menus:0,
         modules:0,
         redirects:0,
         redirectspurge:0,
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
      //  cy.get('header.joomla-dialog-header').should('contain', `Run task (ID: ${task.id})`);
        cy.get('div.scheduler-status').should('contain', 'Status: Completed');
      });

    });

    // Final check: Go to the Trash view and ensure it is empty
    cy.visit('/administrator/index.php?option=com_content&view=articles&filter=[published]=-2');
    cy.get('.display-5').should('exist');
    cy.get('.display-5').should('contain', 'No Articles have been created yet');
    
  });
});
