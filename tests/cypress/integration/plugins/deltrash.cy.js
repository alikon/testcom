describe('Test that the Joomla Task Plugin: Deltrash', () => {
  /**
   * Helper ricorsivo nativo Cypress per attendere che la query DB raggiunga il conteggio atteso.
   */
  const countRows = (sql) =>
    cy.task('queryDB', sql).then((rows) => Number(rows[0].cnt));

  function waitForRowCount(query, expectedCount, maxRetries = 10, delayMs = 500) {
    return countRows(query).then((cnt) => {
      if (cnt === expectedCount) {
        return; // Successo! La condizione è verificata.
      }

      if (maxRetries <= 0) {
        throw new Error(`Timeout: Il conteggio delle righe è ${cnt}, atteso ${expectedCount}`);
      }

      // Aspetta e ritenta ricorsivamente
      cy.wait(delayMs);
      return waitForRowCount(query, expectedCount, maxRetries - 1, delayMs);
    });
  }

  /**
   * Create a deltrash scheduler task and run it via the "Run Task" button.
   */
  const runDeltrashTask = (params, title = 'Test deltrash task') => {
    cy.db_createSchedulerTask({
      title,
      type: 'plg_task_deltrash',
      execution_rules: { 'rule-type': 'manual' },
      cron_rules: { type: 'manual', exp: '' },
      params: {
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
        ...params,
      },
    }).then((task) => {
      cy.visit('/administrator/index.php?option=com_scheduler&view=tasks&filter=');
      cy.searchForItem(title);

      cy.intercept(
        'GET',
        '**/administrator/index.php?option=com_ajax&format=json&plugin=RunSchedulerTest&group=system&id=*'
      ).as('runschedulertest');

      // Attende esplicitamente che il pulsante dello specifico task filtrato sia visibile prima di cliccare
      cy.get(`button[data-scheduler-run][data-id="${task.id}"]`, { timeout: 10000 })
        .should('be.visible')
        .click();

      cy.wait('@runschedulertest').then((interception) => {
        expect(interception.response.body.message).to.eq(null);
        expect(interception.response.body.success).to.eq(true);
      });

      cy.get('joomla-dialog[type="inline"]')
        .should('be.visible')
        .within(() => {
          cy.get('div.scheduler-status')
            .should('contain', 'Status: Completed');
        });
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

    cy.get('div.new-task-details')
      .contains('Delete trashed items')
      .click();

    cy.title().should('contain', 'New Task');
    cy.get('h1.page-title').should('contain', 'New Task');
    cy.get('#general')
      .contains('Delete trashed items')
      .should('be.visible');

    [
      'articles',
      'categories',
      'components',
      'modules',
      'moduletype',
      'redirects',
      'redirectspurge',
      'tags',
      'tasks',
      'contacts',
      'menus',
      'menutype',
    ].forEach((field) => {
      cy.get(
        `[name="jform[params][${field}]"], [name="jform[params][${field}][]"]`
      ).should('exist');
    });
  });

  it('empties trashed articles', () => {
    cy.db_createArticle({
      title: 'Test trash article',
      state: -2,
    });

    runDeltrashTask({
      articles: 1,
    });

    waitForRowCount(
      "SELECT COUNT(*) AS cnt FROM #__content WHERE title = 'Test trash article' AND state = -2",
      0
    );
  });

  it('empties trashed categories for the selected component', () => {
    cy.api_post('/content/categories', {
      title: 'Test trash category',
      description: 'automated test content category description',
      parent_id: 1,
      extension: 'com_content',
      published: -2,
    });    

    runDeltrashTask({
      categories: 1,
      components: ['com_content'],
    });

    waitForRowCount(
      "SELECT COUNT(*) AS cnt FROM #__categories WHERE title = 'Test trash category' AND extension = 'com_content' AND published = -2",
      0
    );
  });

  it('empties trashed admin modules', () => {
    cy.db_createModule({
      title: 'Test trash admin module',
      module: 'mod_menu',
      published: -2,
      client_id: 1,
    });

    runDeltrashTask({
      modules: 1,
      moduletype: ['admin'],
    });

    waitForRowCount(
      "SELECT COUNT(*) AS cnt FROM #__modules WHERE title IN ('Test trash admin module') AND published = -2",
      0
    );
  });

  it('empties trashed site modules', () => {
    cy.db_createModule({
      title: 'Test trash site module',
      module: 'mod_custom',
      published: -2,
      client_id: 0,
    });

    runDeltrashTask({
      modules: 1,
      moduletype: ['site'],
    });

    waitForRowCount(
      "SELECT COUNT(*) AS cnt FROM #__modules WHERE title IN ('Test trash site module') AND published = -2",
      0
    );
  });

  it('empties trashed tags', () => {
    cy.db_createTag({
      title: 'Test trash tag',
      published: -2,
    });

    runDeltrashTask({
      tags: 1,
    });

    waitForRowCount(
      "SELECT COUNT(*) AS cnt FROM #__tags WHERE title = 'Test trash tag' AND published = -2",
      0
    );
  });

  it('empties trashed scheduler tasks', () => {
    cy.db_createSchedulerTask({
      title: 'Trashed task',
      type: 'plg_task_deltrash',
      state: -2,
      execution_rules: { 'rule-type': 'manual' },
      cron_rules: { type: 'manual', exp: '' },
    });

    runDeltrashTask({
      tasks: 1,
    });

    waitForRowCount(
      "SELECT COUNT(*) AS cnt FROM #__scheduler_tasks WHERE title = 'Trashed task' AND state = -2",
      0
    );
  });

  it('empties trashed contacts', () => {
    cy.db_createContact({
      name: 'Test trash contact',
      published: -2,
    });

    runDeltrashTask({
      contacts: 1,
    });

    waitForRowCount(
      "SELECT COUNT(*) AS cnt FROM #__contact_details WHERE name = 'Test trash contact' AND published = -2",
      0
    );
  });

  it('empties trashed site and admin menu items', () => {
    cy.db_createMenuItem({
      title: 'Test trash site menu item',
      published: -2,
      client_id: 0,
    });

    cy.db_createMenuItem({
      title: 'Test trash admin menu item',
      published: -2,
      client_id: 1,
    });

    runDeltrashTask({
      menus: 1,
      menutype: ['site', 'admin'],
    });

    waitForRowCount(
      "SELECT COUNT(*) AS cnt FROM #__menu WHERE title IN ('Test trash site menu item', 'Test trash admin menu item') AND published = -2",
      0
    );
  });

  it('runs all routines together and completes', () => {
    cy.db_createArticle({
      title: 'Combined trash article',
      state: -2,
    });

    cy.db_createTag({
      title: 'Combined trash tag',
      published: -2,
    });

    cy.db_createContact({
      name: 'Combined trash contact',
      published: -2,
    });

    runDeltrashTask(
      {
        articles: 1,
        categories: 0,
        components: [],
        contacts: 1,
        menus: 0,
        menutype: [],
        modules: 0,
        moduletype: [],
        redirects: 0,
        redirectspurge: 0,
        tags: 1,
        tasks: 0,
      },
      'Combined deltrash task'
    );

    waitForRowCount(
      "SELECT COUNT(*) AS cnt FROM #__content WHERE title = 'Combined trash article'",
      0
    );

    waitForRowCount(
      "SELECT COUNT(*) AS cnt FROM #__tags WHERE title = 'Combined trash tag'",
      0
    );

    waitForRowCount(
      "SELECT COUNT(*) AS cnt FROM #__contact_details WHERE name = 'Combined trash contact'",
      0
    );
  });
});


describe('Test that the Joomla Task Plugin: Deltrash runs via CLI', () => {
  const countRows = (sql) =>
    cy.task('queryDB', sql).then((rows) => Number(rows[0].cnt));

  function waitForRowCount(query, expectedCount, maxRetries = 10, delayMs = 500) {
    return countRows(query).then((cnt) => {
      if (cnt === expectedCount) {
        return;
      }

      if (maxRetries <= 0) {
        throw new Error(`Timeout: Il conteggio delle righe è ${cnt}, atteso ${expectedCount}`);
      }

      cy.wait(delayMs);
      return waitForRowCount(query, expectedCount, maxRetries - 1, delayMs);
    });
  }

  /**
   * Create a deltrash task and execute it from the command line.
   */
  const runDeltrashTaskViaCli = (
    paramsOverrides = {},
    title = 'CLI deltrash task'
  ) => {
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

    const mergedParams = {
      ...defaultParams,
      ...paramsOverrides,
    };

    // Genera una data UTC formattata esplicitamente per evitare disallineamenti di fuso orario con MySQL NOW()
    const pastDate = new Date(Date.now() - 10 * 60 * 1000)
      .toISOString()
      .slice(0, 19)
      .replace('T', ' ');

    return cy.db_createSchedulerTask({
      title,
      type: 'plg_task_deltrash',
      state: 1,
      execution_rules: {
        'rule-type': 'interval',
        interval: 1,
        unit: 'minutes',
      },
      cron_rules: {
        type: 'interval',
        exp: '* * * * *',
      },
      params: mergedParams,
    }).then((task) =>
      cy.task(
        'queryDB',
        `UPDATE #__scheduler_tasks
         SET next_execution = '${pastDate}'
         WHERE id = ${task.id}`
      ).then(() =>
        cy.exec(
          `php ${Cypress.expose('cmsPath')}/cli/joomla.php scheduler:run`,
          {
            timeout: 130000,
            failOnNonZeroExit: false,
          }
        ).then((result) => {
          expect(
            result.exitCode,
            `CLI run output:\nstdout: ${result.stdout}\nstderr: ${result.stderr}`
          ).to.eq(0);

          return cy.wrap(task);
        })
      )
    );
  };

  beforeEach(() => {
    cy.task('clearEmails');
    cy.db_enableExtension('1', 'plg_task_deltrash');
  });

  it('empties trashed articles when run from the CLI', () => {
    cy.db_createArticle({
      title: 'CLI trash article',
      state: -2,
    });

    waitForRowCount(
      "SELECT COUNT(*) AS cnt FROM #__content WHERE title = 'CLI trash article' AND state = -2",
      1
    );

    runDeltrashTaskViaCli(
      { articles: 1 },
      'CLI articles task'
    ).then(() => {
      waitForRowCount(
        "SELECT COUNT(*) AS cnt FROM #__content WHERE title = 'CLI trash article'",
        0
      );

      waitForRowCount(
        "SELECT COUNT(*) AS cnt FROM #__ucm_content WHERE core_type_alias = 'com_content.article' AND core_content_item_id NOT IN (SELECT id FROM #__content)",
        0
      );
    });
  });

  it('cleans article auxiliary data (tag map) when run from the CLI', () => {
    cy.db_createArticle({
      title: 'CLI tagged trash article',
      state: -2,
    });

    cy.db_createTag({
      title: 'cli-test-tag',
      published: 1,
    });

    cy.task(
      'queryDB',
      "SELECT id FROM #__content WHERE title = 'CLI tagged trash article' LIMIT 1"
    ).then((artRows) => {
      const articleId = artRows[0].id;

      cy.task(
        'queryDB',
        "SELECT id FROM #__tags WHERE title = 'cli-test-tag' LIMIT 1"
      ).then((tagRows) => {
        const tagId = tagRows[0].id;

        cy.task(
          'queryDB',
          `INSERT INTO #__contentitem_tag_map
           (tag_id, content_item_id, type_alias, type_id, core_content_id)
           VALUES
           (${tagId}, ${articleId}, 'com_content.article', 1, 0)`
        ).then(() => {
          waitForRowCount(
            "SELECT COUNT(*) AS cnt FROM #__contentitem_tag_map WHERE type_alias = 'com_content.article'",
            1
          );

          runDeltrashTaskViaCli(
            { articles: 1 },
            'CLI aux cleanup task'
          ).then(() => {
            waitForRowCount(
              "SELECT COUNT(*) AS cnt FROM #__contentitem_tag_map WHERE type_alias = 'com_content.article' AND content_item_id NOT IN (SELECT id FROM #__content)",
              0
            );
          });
        });
      });
    });
  });

  it('empties trashed categories via CLI', () => {
    cy.api_post('/content/categories', {
      title: 'Test trash category',
      description: 'automated test content category description',
      parent_id: 1,
      extension: 'com_content',
      published: -2,
    });

    runDeltrashTaskViaCli(
      {
        categories: 1,
        components: ['com_content'],
      },
      'CLI categories task'
    ).then(() => {
      waitForRowCount(
        "SELECT COUNT(*) AS cnt FROM #__categories WHERE title = 'CLI trash category' AND published = -2",
        0
      );
    });
  });

  it('empties trashed tags via CLI', () => {
    cy.db_createTag({
      title: 'CLI trash tag',
      published: -2,
    });

    runDeltrashTaskViaCli(
      { tags: 1 },
      'CLI tags task'
    ).then(() => {
      waitForRowCount(
        "SELECT COUNT(*) AS cnt FROM #__tags WHERE title = 'CLI trash tag' AND published = -2",
        0
      );
    });
  });

  it('empties trashed modules via CLI', () => {
    cy.db_createModule({
      title: 'CLI trash module',
      module: 'mod_custom',
      published: -2,
      client_id: 0,
    });

    runDeltrashTaskViaCli(
      {
        modules: 1,
        moduletype: ['site'],
      },
      'CLI modules task'
    ).then(() => {
      waitForRowCount(
        "SELECT COUNT(*) AS cnt FROM #__modules WHERE title = 'CLI trash module' AND published = -2",
        0
      );
    });
  });

  it('empties trashed contacts via CLI', () => {
    cy.db_createContact({
      name: 'CLI trash contact',
      published: -2,
    });

    runDeltrashTaskViaCli(
      { contacts: 1 },
      'CLI contacts task'
    ).then(() => {
      waitForRowCount(
        "SELECT COUNT(*) AS cnt FROM #__contact_details WHERE name = 'CLI trash contact' AND published = -2",
        0
      );
    });
  });

  it('empties trashed site and admin menu items via CLI', () => {
    cy.db_createMenuItem({
      title: 'CLI trash site menu item',
      published: -2,
      client_id: 0,
    });

    cy.db_createMenuItem({
      title: 'CLI trash admin menu item',
      published: -2,
      client_id: 1,
    });

    runDeltrashTaskViaCli(
      {
        menus: 1,
        menutype: ['site', 'admin'],
      },
      'CLI menus task'
    ).then(() => {
      waitForRowCount(
        "SELECT COUNT(*) AS cnt FROM #__menu WHERE title IN ('CLI trash site menu item', 'CLI trash admin menu item') AND published = -2",
        0
      );
    });
  });

  it('completes successfully when the trash is already empty', () => {
    runDeltrashTaskViaCli(
      {
        articles: 1,
        tags: 1,
        contacts: 1,
      },
      'CLI empty trash task'
    );
  });

  it('runs all routines together via CLI', () => {
    cy.db_createArticle({
      title: 'CLI combined article',
      state: -2,
    });

    cy.db_createTag({
      title: 'CLI combined tag',
      published: -2,
    });

    cy.db_createContact({
      name: 'CLI combined contact',
      published: -2,
    });

    cy.api_post('/content/categories', {
      title: 'CLI combined category',
      description: 'automated test content category description',
      parent_id: 1,
      extension: 'com_content',
      published: -2,
    });

    runDeltrashTaskViaCli(
      {
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
      },
      'CLI combined deltrash task'
    ).then(() => {
      waitForRowCount(
        "SELECT COUNT(*) AS cnt FROM #__content WHERE title = 'CLI combined article'",
        0
      );

      waitForRowCount(
        "SELECT COUNT(*) AS cnt FROM #__tags WHERE title = 'CLI combined tag'",
        0
      );

      waitForRowCount(
        "SELECT COUNT(*) AS cnt FROM #__contact_details WHERE name = 'CLI combined contact'",
        0
      );

      waitForRowCount(
        "SELECT COUNT(*) AS cnt FROM #__categories WHERE title = 'CLI combined category' AND published = -2",
        0
      );
    });
  });
});
