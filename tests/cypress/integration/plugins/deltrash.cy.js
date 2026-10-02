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

describe('Test that the Joomla Task Plugin: Deltrash runs via CLI', () => {
  /**
   * Create a deltrash task and execute it from the command line:
   *   php cli/joomla.php scheduler:run --task=<id>
   */
  const runDeltrashTaskViaCli = (paramsOverrides = {}, title = 'CLI deltrash task') => {
    const defaultParams = {
      notifications: { success_mail: 0 },
      articles: 0,
      categories: 0,
      components: [],
      contacts: 0,
      menus: 0,
      menutype: [],
      modules: 0,
      moduletype: [],
      redirects: 0,
      redirectspurge: 0,
      tags: 0,
      tasks: 0,
    };

    const mergedParams = { ...defaultParams, ...paramsOverrides };

    return cy.db_createSchedulerTask({
      title,
      type: 'plg_task_deltrash',
      state: 1,
      execution_rules: { 'rule-type': 'interval', interval: 1, unit: 'minutes' },
      cron_rules: { type: 'interval', exp: '* * * * *' },
      params: mergedParams,
    }).then((task) =>
      cy.task(
        'queryDB',
        `UPDATE #__scheduler_tasks SET next_execution = (NOW() - INTERVAL 5 MINUTE) WHERE id = ${task.id}`
      ).then(() =>
        cy.exec(`php ${Cypress.expose('cmsPath')}/cli/joomla.php scheduler:run`, {
          timeout: 60000,
          failOnNonZeroExit: false,
        }).then((result) => {
          expect(
            result.exitCode,
            `CLI run output:\nstdout: ${result.stdout}\nstderr: ${result.stderr}`
          ).to.eq(0);

          return cy.wrap(task);
        })
      )
    );
  };

  const countRows = (sql) =>
    cy.task('queryDB', sql).then((rows) => Number(rows[0].cnt));

  beforeEach(() => {
    cy.task('clearEmails');
    cy.db_enableExtension('1', 'plg_task_deltrash');
  });

  it('empties trashed articles when run from the CLI', () => {
    cy.db_createArticle({ title: 'CLI trash article', state: -2 }).then(() => {
      countRows("SELECT COUNT(*) AS cnt FROM #__content WHERE title = 'CLI trash article' AND state = -2")
        .should('be.greaterThan', 0);
    });

    runDeltrashTaskViaCli({ articles: 1 }, 'CLI articles task').then(() => {
      countRows("SELECT COUNT(*) AS cnt FROM #__content WHERE title = 'CLI trash article'")
        .should('eq', 0);

      countRows(
        "SELECT COUNT(*) AS cnt FROM #__ucm_content WHERE core_type_alias = 'com_content.article'" +
        ' AND core_content_item_id NOT IN (SELECT id FROM #__content)'
      ).should('eq', 0);
    });
  });

  it('cleans article auxiliary data (tag map) when run from the CLI', () => {
    cy.db_createArticle({ title: 'CLI tagged trash article', state: -2 });
    cy.db_createTag({ title: 'cli-test-tag', published: 1 });

    cy.task('queryDB', "SELECT id FROM #__content WHERE title = 'CLI tagged trash article' LIMIT 1").then((artRows) => {
      const articleId = artRows[0].id;

      cy.task('queryDB', "SELECT id FROM #__tags WHERE title = 'cli-test-tag' LIMIT 1").then((tagRows) => {
        const tagId = tagRows[0].id;

        // Query corretta per la tabella #__contentitem_tag_map
        cy.task(
          'queryDB',
          `INSERT INTO #__contentitem_tag_map (tag_id, content_item_id, type_alias, type_id, core_content_id)` +
          ` VALUES (${tagId}, ${articleId}, 'com_content.article', 1, 0)`
        ).then(() => {
          countRows("SELECT COUNT(*) AS cnt FROM #__contentitem_tag_map WHERE type_alias = 'com_content.article'")
            .should('be.greaterThan', 0);

          runDeltrashTaskViaCli({ articles: 1 }, 'CLI aux cleanup task').then(() => {
            countRows(
              "SELECT COUNT(*) AS cnt FROM #__contentitem_tag_map WHERE type_alias = 'com_content.article'" +
              ' AND content_item_id NOT IN (SELECT id FROM #__content)'
            ).should('eq', 0);
          });
        });
      });
    });
  });

  it('empties trashed categories via CLI', () => {
    cy.db_createCategory({ title: 'CLI trash category', extension: 'com_content', published: -2 });

    runDeltrashTaskViaCli({ categories: 1, components: ['com_content'] }, 'CLI categories task').then(() => {
      countRows("SELECT COUNT(*) AS cnt FROM #__categories WHERE title = 'CLI trash category' AND published = -2")
        .should('eq', 0);
    });
  });

  it('empties trashed tags via CLI', () => {
    cy.db_createTag({ title: 'CLI trash tag', published: -2 });

    runDeltrashTaskViaCli({ tags: 1 }, 'CLI tags task').then(() => {
      countRows("SELECT COUNT(*) AS cnt FROM #__tags WHERE title = 'CLI trash tag' AND published = -2")
        .should('eq', 0);
    });
  });

 it('empties trashed modules via CLI', () => {
    // Creiamo il modulo nel cestino (published: -2) con il suo module type specifico
    cy.db_createModule({ title: 'CLI trash module', module: 'mod_custom', published: -2, client_id: 0 });

    // Passiamo moduletype popolato con il tipo specifico del modulo oppure 'mod_custom'
    runDeltrashTaskViaCli({ modules: 1, moduletype: ['site'] }, 'CLI modules task').then(() => {
      countRows("SELECT COUNT(*) AS cnt FROM #__modules WHERE title = 'CLI trash module' AND published = -2")
      .should('eq', 0);
    });
  });

  it('empties trashed contacts via CLI', () => {
    cy.db_createContact({ name: 'CLI trash contact', published: -2 });

    runDeltrashTaskViaCli({ contacts: 1 }, 'CLI contacts task').then(() => {
      countRows("SELECT COUNT(*) AS cnt FROM #__contact_details WHERE name = 'CLI trash contact' AND published = -2")
        .should('eq', 0);
    });
  });

  it('empties trashed site and admin menu items via CLI', () => {
    cy.db_createMenuItem({ title: 'CLI trash site menu item', published: -2, client_id: 0 });
    cy.db_createMenuItem({ title: 'CLI trash admin menu item', published: -2, client_id: 1 });

    runDeltrashTaskViaCli({ menus: 1, menutype: ['site', 'admin'] }, 'CLI menus task').then(() => {
      countRows("SELECT COUNT(*) AS cnt FROM #__menu WHERE title = 'CLI trash site menu item' AND published = -2")
        .should('eq', 0);
      countRows("SELECT COUNT(*) AS cnt FROM #__menu WHERE title = 'CLI trash admin menu item' AND published = -2")
        .should('eq', 0);
    });
  });

  it('completes successfully when the trash is already empty', () => {
    runDeltrashTaskViaCli({ articles: 1, tags: 1, contacts: 1 }, 'CLI empty trash task');
  });

  it('runs all routines together via CLI', () => {
    cy.db_createArticle({ title: 'CLI combined article', state: -2 });
    cy.db_createTag({ title: 'CLI combined tag', published: -2 });
    cy.db_createContact({ name: 'CLI combined contact', published: -2 });
    cy.db_createCategory({ title: 'CLI combined category', extension: 'com_content', published: -2 });

    runDeltrashTaskViaCli({
      articles: 1,
      categories: 1,
      components: ['com_content'],
      contacts: 1,
      menus: 0,
      menutype: [],
      modules: 0,
      moduletype: [],
      redirects: 0,
      redirectspurge: 0,
      tags: 1,
      tasks: 0,
    }, 'CLI combined deltrash task').then(() => {
      countRows("SELECT COUNT(*) AS cnt FROM #__content WHERE title = 'CLI combined article'").should('eq', 0);
      countRows("SELECT COUNT(*) AS cnt FROM #__tags WHERE title = 'CLI combined tag'").should('eq', 0);
      countRows("SELECT COUNT(*) AS cnt FROM #__contact_details WHERE name = 'CLI combined contact'").should('eq', 0);
      countRows("SELECT COUNT(*) AS cnt FROM #__categories WHERE title = 'CLI combined category' AND published = -2").should('eq', 0);
    });
  });
});
