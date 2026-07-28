describe('Extension Upgrade Test for alikon/testcom (Latest Release -> PR Candidate)', () => {
  beforeEach(() => {
    cy.doAdministratorLogin();
  });

  it('uninstalls PR candidate, installs latest stable release, creates data, and updates to PR candidate', () => {
    // ------------------------------------------------------------------
    // 1. Uninstall current PR version (e.g., MagicLogin) installed during environment setup
    // ------------------------------------------------------------------
    cy.visit('administrator/index.php?option=com_installer&view=manage');
    cy.searchForItem('plg_system_magiclogin'); // Search for the specific plugin

    cy.get('body').then(($body) => {
      if ($body.find('table tbody tr').length > 0) {
        cy.checkAllResults();
        cy.clickToolbarButton('delete');
        cy.get('#system-message-container').should('contain', 'Uninstalling the plugin was successful');
      }
    });

    // ------------------------------------------------------------------
    // 2. Fetch and install latest stable release (n-1) of MagicLogin from GitHub
    // ------------------------------------------------------------------
    cy.request('https://api.github.com/repos/alikon/testcom/releases/latest').then((response) => {
      // Find the specific asset for the MagicLogin plugin
      const zipAsset = response.body.assets.find((asset) => asset.name.startsWith('plg_system_magiclogin') && asset.name.endsWith('.zip'));
      expect(zipAsset, 'Latest MagicLogin release zip asset found').to.exist;

      cy.installExtensionFromUrl(zipAsset.browser_download_url);
      cy.get('#system-message-container').should('contain', 'Installation of the plugin was successful');
    });

    // ------------------------------------------------------------------
    // 3. Create sample data / configuration for MagicLogin in version n-1
    // ------------------------------------------------------------------
    // This step is HIGHLY specific to the extension.
    // For MagicLogin, you might enable the plugin and set a parameter.
    // Example: Enable the plugin
    cy.visit('administrator/index.php?option=com_plugins&view=plugins&filter_search=plg_system_magiclogin');
    cy.get('tbody tr').contains('System - Magic Login').click(); // Click to edit
    cy.get('#jform_enabled').select('1'); // Set enabled to Yes
    cy.clickToolbarButton('save');
    cy.get('#system-message-container').should('contain', 'Plugin saved');

    // ------------------------------------------------------------------
    // 4. Upgrade using the current PR Candidate package (n) - e.g., from a local build
    // ------------------------------------------------------------------
    // This assumes you have a built ZIP of the PR candidate in your environment.
    const prCandidateUrl = `${Cypress.config('baseUrl')}/plg_system_magiclogin-current.zip`; 
    cy.installExtensionFromUrl(prCandidateUrl);
    cy.get('#system-message-container').should('contain', 'Installation of the plugin was successful');

    // ------------------------------------------------------------------
    // 5. Verify data/configuration survived the update
    // ------------------------------------------------------------------
    // For MagicLogin, verify the plugin is still enabled.
    cy.visit('administrator/index.php?option=com_plugins&view=plugins&filter_search=plg_system_magiclogin');
    cy.get('tbody tr').contains('System - MagicLogin').parent().find('.badge-success').should('exist'); // Check if enabled badge is present
  });
});
