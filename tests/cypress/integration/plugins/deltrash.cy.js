describe('Test that the Joomla Task Plugin: Deltrash', () => {
  /**
   * Create a deltrash scheduler task with the given params, run it via the
   * "Run Task" button and assert it completes successfully.
   */
  const runDeltrashTask = (params, title = 'Test deltrash task') => {
    cy.db_createSchedulerTask({
      title,
      type: 'plg_task_deltrash',
      execution_rules: { 'rule-type': 'manual' },
      cron_rules: { type: 'manual', exp: '' },
      params: {
        notifications: { success_mail: 0 },
        articles: 0, categories: 0, components: [], contacts: 0,
        menus: 0, menutype: [], modules: 0, moduletype: [],
        redirects: 0, redirectspurge: 0, tags: 0, tasks: 0,
        ...params,
      },
    }).then((task) => {
      cy.visit('/administrator/index.php?option=com_scheduler&view=tasks&filter=');
      cy.searchForItem(title);
      cy.intercept('GET', '**/administrator/index.php?option=com_ajax&format=json&plugin=RunSchedulerTest&group=system&id=*')
        .as('runschedulertest');
      cy.get('button[data-scheduler-run]').should('have.attr', 'data-id', task.id).click();
      cy.wait('@runschedulertest').then((interception) => {
        expect(interception.response.body.message).to.eq(null);
        expect(interception.response.body.success).to.eq(true);
      });
      cy.get('joomla-dialog[type="inline"]').should('be.visible').within(() => {
        cy.get('div.scheduler-status').should('contain', 'Status: Completed');
      });
    });
  };

  const waitForCategoryDeleted = (deadline = Date.now() + 10000) => {
    return cy.task('queryDB', "SELECT COUNT(*) as cnt FROM #__categories WHERE extension='com_content' AND published=-2 AND title='Test trash category'")
      .then((rows) => {
        if (rows[0].cnt > 0) {
          if (Date.now() > deadline) {
            throw new Error('Timeout: la categoria trashed non è stata cancellata in tempo');
          }
          return cy.wait(300).then(() => waitForCategoryDeleted(deadline));
        }
      });
  };

  beforeEach(() => {
    cy.task('clearEmails');
    cy.doAdministratorLogin();
    cy.db_enableExtension('1', 'plg_task_deltrash');
  });

  it('can display the plugin form', () => {
    cy.visit('/administrator/index.php?option=com_scheduler&view=tasks');
    cy.get('#toolbar-new', { timeout: 40000 }).should('be.visible');
    cy.clickToolbarButton('New');
    cy.get('div.new-task-details').contains('Delete trashed items').click();
    cy.title().should('contain', 'New Task');
    cy.get('h1.page-title').should('contain', 'New Task');
    cy.get('#general').contains('Delete trashed items').should('be.visible');

    // Verify all option fields from deltrash_parameters form are present
    ['articles', 'categories', 'components', 'modules', 'moduletype',
      'redirects', 'redirectspurge', 'tags', 'tasks', 'contacts', 'menus', 'menutype',
    ].forEach((field) => {
      cy.get(`[name="jform[params][${field}]"], [name="jform[params][${field}][]"]`).should('exist');
    });
  });

  it('empties trashed articles', () => {
    cy.db_createArticle({ title: 'Test trash article', state: -2 });
    runDeltrashTask({ articles: 1 });
    cy.visit('/administrator/index.php?option=com_content&view=articles&filter[published]=-2');
    // cy.get('.display-5').should('contain', 'No Articles have been created yet');
    // cy.checkForSystemMessage('No Matching Results');
    cy.contains('No Articles have been created yet').should('exist');
  });

  it('empties trashed categories for the selected component', () => {
    // cy.db_createCategory({ title: 'Test trash category', extension: 'com_content', published: -2 });
    cy.visit('/administrator/index.php?option=com_categories&task=category.add&extension=com_content');
    cy.get('#jform_title').should('exist').type('Test category');
    cy.get('#jform_published').should('exist').select('Trashed');
    cy.clickToolbarButton('Save & Close');
    runDeltrashTask({ categories: 1, components: ['com_content'] });
    // waitForCategoryDeleted();
    cy.visit('/administrator/index.php?option=com_categories&view=categories&extension=com_content&filter[published]=-2');
    cy.contains('No Matching Results').should('exist');
  });

  it('empties trashed site and admin modules', () => {
    cy.db_createModule({ title: 'Test trash site module', published: -2, client_id: 0 });
    cy.db_createModule({ title: 'Test trash admin module', published: -2, client_id: 1 });
    runDeltrashTask({ modules: 1, moduletype: ['site', 'admin'] });
    cy.visit('/administrator/index.php?option=com_modules&view=modules&filter[state]=-2');
    cy.contains('There are no modules matching your query').should('exist');
    cy.visit('/administrator/index.php?option=com_modules&view=modules&client_id=1&filter[state]=-2');
    cy.contains('There are no modules matching your query').should('exist');
  });
  /*
  it('empties trashed redirects', () => {
    cy.db_createRedirect({ old_url: '/test-trash-redirect', new_url: '/index.php', published: -2 });
    runDeltrashTask({ redirects: 1, redirectspurge: 0 });
    cy.visit('/administrator/index.php?option=com_redirect&view=links&filter[state]=-2');
    cy.contains('No Matching Results').should('exist');
  });

  it('purges all redirects when redirectspurge is enabled', () => {
    cy.db_createRedirect({ old_url: '/test-purge-redirect', new_url: '/index.php', published: 1 });
    runDeltrashTask({ redirects: 1, redirectspurge: 1 });
    cy.visit('/administrator/index.php?option=com_redirect&view=links');
    cy.contains('No Matching Results').should('exist');
  });
 */
  it('empties trashed tags', () => {
    cy.db_createTag({ title: 'Test trash tag', published: -2 });
    runDeltrashTask({ tags: 1 });
    cy.visit('/administrator/index.php?option=com_tags&view=tags&filter[published]=-2');
    cy.contains('No Tags have been created yet').should('exist');
  });

  it('empties trashed scheduler tasks', () => {
    cy.db_createSchedulerTask({
      title: 'Trashed task', type: 'plg_task_deltrash', state: -2,
      execution_rules: { 'rule-type': 'manual' },
      cron_rules: { type: 'manual', exp: '' },
    });
    runDeltrashTask({ tasks: 1 });
    cy.visit('/administrator/index.php?option=com_scheduler&view=tasks&filter[state]=-2');
    cy.contains('No Matching Results').should('exist');
  });

  it('empties trashed contacts', () => {
    cy.db_createContact({ name: 'Test trash contact', published: -2 });
    runDeltrashTask({ contacts: 1 });
    cy.visit('/administrator/index.php?option=com_contact&view=contacts&filter[published]=-2');
    cy.contains('No Contacts have been created yet').should('exist');
  });

  it('empties trashed site and admin menu items', () => {
    cy.db_createMenuItem({ title: 'Test trash site menu item', published: -2, client_id: 0 });
    cy.db_createMenuItem({ title: 'Test trash admin menu item', published: -2, client_id: 1 });
    runDeltrashTask({ menus: 1, menutype: ['site', 'admin'] });
    cy.visit('/administrator/index.php?option=com_menus&view=items&menutype=mainmenu&filter[published]=-2');
    cy.contains('No Matching Results').should('exist');
  });

  it('runs all routines together and completes', () => {
    cy.db_createArticle({ title: 'Combined trash article', state: -2 });
    cy.db_createTag({ title: 'Combined trash tag', published: -2 });
    cy.db_createContact({ name: 'Combined trash contact', published: -2 });
    runDeltrashTask({
      articles: 1, categories: 1, components: ['com_content'],
      contacts: 1, menus: 1, menutype: ['site'],
      modules: 1, moduletype: ['site'],
      redirects: 1, redirectspurge: 0, tags: 1, tasks: 0,
    }, 'Combined deltrash task');
    cy.visit('/administrator/index.php?option=com_content&view=articles&filter[published]=-2');
    cy.contains('No Articles have been created yet').should('exist');
  });
});
