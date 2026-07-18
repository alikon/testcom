describe('Test in backend that the content Export plugin', () => {
  // Same-origin fake remote target so cy.intercept works without any CORS
  // dance: fetch() calls issued by aexport.js are intercepted by Cypress
  // before they ever leave the browser.
  const remoteDomain = `${Cypress.config('baseUrl')}fakeremoteapi`.replace(/\/+$/, '');
  const pluginCatid = 100;
  const pluginState = 1;

  const configurePlugin = () => {
    const params = {
      url: remoteDomain,
      authorization: 'X-Joomla-Token',
      key: 'test-token',
      catid: pluginCatid,
      state: pluginState,
      timeout: 5,
    };

    return cy.wrap(Object.entries(params), { log: false })
      .each(([key, value]) => cy.db_updateExtensionParameter(key, value, 'plg_content_export'));
  };

  const stubRemoteApi = () => {
    // checkCategory(): any 2xx response is treated as "category is valid".
    cy.intercept('GET', `${remoteDomain}/api/index.php/v1/content/categories/*`, {
      statusCode: 200,
      body: {},
    }).as('remoteCategoryCheck');

    // checkArticle(): pretend the article never existed remotely, so the
    // client always goes through postArticle() (creation).
    cy.intercept('GET', `${remoteDomain}/api/index.php/v1/content/articles*`, {
      statusCode: 200,
      body: { data: [] },
    }).as('remoteArticleSearch');

    // postArticle(): pretend creation succeeded.
    cy.intercept('POST', `${remoteDomain}/api/index.php/v1/content/articles`, {
      statusCode: 200,
      body: { data: { id: 999 } },
    }).as('remoteArticleCreate');
  };

  const getCsrfToken = () => cy.window().its('Joomla').invoke('getOptions', 'csrf.token');

  beforeEach(() => {
    cy.doAdministratorLogin();
    cy.db_enableExtension('1', 'plg_content_export');
    configurePlugin();
  });

  afterEach(() => {
    cy.task('queryDB', "DELETE FROM #__content WHERE title LIKE 'Test export article%'");
  });

  it('shows the Export button on the articles list view', () => {
    cy.visit('/administrator/index.php?option=com_content&view=articles&filter=');
    cy.get('#toolbar-upload').should('exist').and('contain.text', 'Export');
  });

  it('shows the Export button on the single article view', () => {
    cy.db_createArticle({ title: 'Test export article single' }).then((article) => {
      cy.visit(`/administrator/index.php?option=com_content&task=article.edit&id=${article.id}`);
      cy.get('#toolbar-upload').should('exist').and('contain.text', 'Export');
    });
  });

  it('shows an error and does not call the local AJAX endpoint when no article is selected', () => {
    stubRemoteApi();
    cy.intercept('POST', '**/index.php?option=com_ajax&plugin=export&group=content&format=json').as('bulkExportAjax');

    cy.visit('/administrator/index.php?option=com_content&view=articles&filter=');
    cy.get('#toolbar-upload').click();

    cy.get('#msg').should('contain.text', 'Select at least one article from the list');
    cy.get('@bulkExportAjax.all').should('have.length', 0);
  });

  it('bulk-exports the selected articles, enforcing plugin catid/state from configuration', () => {
    cy.db_createArticle({
      title: 'Test export article 1',
      introtext: '<p>First intro</p>',
      state: 1,
      // Deliberately different from the plugin-configured catid/state,
      // to prove the server overrides them rather than trusting the row.
      catid: 2,
    }).then((article1) => {
      cy.db_createArticle({
        title: 'Test export article 2',
        introtext: '<p>Second intro</p>',
        state: 0,
        catid: 2,
      }).then((article2) => {
        stubRemoteApi();
        cy.intercept('POST', '**/index.php?option=com_ajax&plugin=export&group=content&format=json').as('bulkExportAjax');

        cy.visit('/administrator/index.php?option=com_content&view=articles&filter=');
        cy.searchForItem('Test export article');
        cy.checkAllResults();
        cy.get('#toolbar-upload').click();

        cy.wait('@bulkExportAjax').then((interception) => {
          expect(interception.response.statusCode).to.eq(200);
          const { body } = interception.response;
          expect(body.success).to.eq(true);

          const articles = body.data[0];
          expect(articles).to.have.length(2);

          const titles = articles.map((a) => a.title).sort();
          expect(titles).to.deep.eq(['Test export article 1', 'Test export article 2']);

          // The plugin must force its own catid/state, ignoring the
          // article's actual values.
          articles.forEach((exported) => {
            expect(exported.catid).to.eq(pluginCatid);
            expect(exported.state).to.eq(pluginState);
          });
        });

        // Two remote creations should follow, one per exported article.
        cy.wait('@remoteArticleCreate');
        cy.wait('@remoteArticleCreate');

        cy.get('#msg').should('contain.text', 'Bulk export complete');
      });
    });
  });

  it('never returns trashed articles even if their ID is submitted directly to the AJAX endpoint', () => {
    cy.db_createArticle({ title: 'Test export article trashed', state: -2 }).then((trashed) => {
      cy.visit('/administrator/index.php?option=com_content&view=articles&filter=');

      getCsrfToken().then((token) => {
        cy.request({
          method: 'POST',
          url: '/administrator/index.php?option=com_ajax&plugin=export&group=content&format=json',
          form: true,
          body: { [token]: 1, 'ids[]': [trashed.id] },
        }).then((response) => {
          expect(response.status).to.eq(200);
          expect(response.body.success).to.eq(true);
          expect(response.body.data[0]).to.have.length(0);
        });
      });
    });
  });

  it('rejects the AJAX request when it is not a valid POST with a CSRF token', () => {
    cy.visit('/administrator/index.php?option=com_content&view=articles&filter=');

    cy.request({
      method: 'POST',
      url: '/administrator/index.php?option=com_ajax&plugin=export&group=content&format=json',
      form: true,
      body: { 'ids[]': [1] }, // no CSRF token field
      failOnStatusCode: false,
    }).then((response) => {
      expect(response.body.success).to.eq(false);
    });
  });

  it('rejects a bulk export request that exceeds the configured ID limit', () => {
    cy.visit('/administrator/index.php?option=com_content&view=articles&filter=');

    getCsrfToken().then((token) => {
      // MAX_BULK_IDS on the server is 200: send one more than that.
      const tooManyIds = Array.from({ length: 201 }, (_, i) => i + 1);

      cy.request({
        method: 'POST',
        url: '/administrator/index.php?option=com_ajax&plugin=export&group=content&format=json',
        form: true,
        body: { [token]: 1, 'ids[]': tooManyIds },
        failOnStatusCode: false,
      }).then((response) => {
        expect(response.body.success).to.eq(false);
        expect(response.body.message).to.contain('200');
      });
    });
  });
});
