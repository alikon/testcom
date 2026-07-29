// tests/cypress/integration/.../Update_AlikonwebPackage.cy.js
describe('Package Upgrade Test for alikon/testcom (Latest Release -> PR Candidate, mocked update)', () => {
  // Confermati dal manifest pkg_alikonweb.xml
  const PACKAGE_ELEMENT = 'pkg_alikonweb';
  const PACKAGE_NAME = 'pkg_alikonweb'; // <name> nel manifest, nessuna stringa di lingua da risolvere

  // ATTENZIONE: l'element DB del plugin è "magiclogin" (dal tag id=""),
  // non "plg_system_magiclogin" (quello è solo il nome del file zip)
  const PLUGIN_ELEMENT = 'magiclogin';
  const PLUGIN_NAME = 'System - Magic Login'; // nome visualizzato in com_plugins

  const PR_ZIP_PUBLIC_URL = `${Cypress.config('baseUrl')}/pkg-alikonweb-current.zip`;
  const CMS_PATH = Cypress.expose('cmsPath'); // es. /tests/www/mysql
  const FAKE_UPDATE_XML_RELATIVE = 'pr-build/update.xml';
  const FAKE_UPDATE_XML_PUBLIC_URL = `${Cypress.config('baseUrl')}/${FAKE_UPDATE_XML_RELATIVE}`;
  const FAKE_VERSION = '99.99.99';

  beforeEach(() => {
    cy.doAdministratorLogin();
  });

  it('uninstalls PR candidate package, installs latest stable, mocks an update, applies it', () => {
    // ------------------------------------------------------------------
    // 1. Disinstalla il package PR già installato durante il setup CI
    //    (rimuove in cascata anche tutte le sotto-estensioni)
    // ------------------------------------------------------------------
    cy.visit('administrator/index.php?option=com_installer&view=manage');
    cy.searchForItem('pkg_alikonweb');
    cy.get('body').then(($body) => {
      if ($body.find('table tbody tr').length > 0) {
        cy.checkAllResults();
        cy.get('button.button-status-group.btn.btn-action.dropdown-toggle').click();
        // Second click on the 'Uninstall' button
        cy.get('button.button-delete.dropdown-item').click();
        // Third click on the 'Yes' button to confirm
        cy.get('div.joomla-dialog-container')
          .find('button.button.button-primary.btn.btn-primary[data-button-ok]')
          .click();
        cy.checkForSystemMessage('was successful')
      }
    });

    // ------------------------------------------------------------------
    // 2. Installa il package dell'ultima release stabile da GitHub
    // ------------------------------------------------------------------
    cy.request('https://api.github.com/repos/alikon/testcom/releases/latest').then((response) => {
      const zipAsset = response.body.assets.find(
        (asset) => asset.name.startsWith('pkg-alikonweb') && asset.name.endsWith('.zip')
      );
      expect(zipAsset, 'Latest package zip asset found').to.exist;

      cy.installExtensionFromUrl(zipAsset.browser_download_url);
      cy.get('#system-message-container').should('contain', 'Installation of the package was successful');
    });

    // ------------------------------------------------------------------
    // 3. Configura dati/stato sulla sotto-estensione (MagicLogin),
    //    che deve sopravvivere all'update dell'intero package
    // ------------------------------------------------------------------
    cy.visit('administrator/index.php?option=com_plugins&view=plugins');
    cy.searchForItem(PLUGIN_NAME);
    cy.checkAllResults();
    cy.contains('Enable').click();
    cy.on('window:confirm', () => true);
    cy.checkForSystemMessage('Plugin enabled.');

    // ------------------------------------------------------------------
    // 4. Genera l'update.xml fasullo per il PACKAGE direttamente inline
    //    (nessuna fixture: il template vive qui, nel test) e scrivilo
    //    nel webroot Joomla, in modo che il backend PHP possa scaricarlo
    //    via HTTP durante "Find Updates"
    // ------------------------------------------------------------------
    const fakeUpdateXml = `<?xml version="1.0" encoding="utf-8"?>
<updates>
  <update>
    <name>${PACKAGE_NAME}</name>
    <description>PR candidate build (mocked update)</description>
    <element>${PACKAGE_ELEMENT}</element>
    <type>package</type>
    <version>${FAKE_VERSION}</version>
    <infourl title="testcom">https://github.com/alikon/testcom</infourl>
    <downloads>
      <downloadurl type="full" format="zip">${PR_ZIP_PUBLIC_URL}</downloadurl>
    </downloads>
    <targetplatform name="joomla" version="(5|6)\\.\\d+\\.\\d+"/>
  </update>
</updates>`;

    cy.writeFile(`${CMS_PATH}/${FAKE_UPDATE_XML_RELATIVE}`, fakeUpdateXml);

    // ------------------------------------------------------------------
    // 5. Punta l'update site DEL PACKAGE verso il nostro update.xml
    // ------------------------------------------------------------------
    cy.visit('administrator/index.php?option=com_installer&view=updatesites');
    cy.searchForItem(PACKAGE_NAME);
    cy.get('table tbody tr').contains(PACKAGE_NAME).parents('tr').within(() => {
      cy.get('a').first().click();
    });
    cy.get('#jform_location').clear().type(FAKE_UPDATE_XML_PUBLIC_URL);
    cy.clickToolbarButton('save-close');
    cy.checkForSystemMessage('Update site saved');

    // ------------------------------------------------------------------
    // 6. Forza Joomla a rilevare l'update del package
    // ------------------------------------------------------------------
    cy.visit('administrator/index.php?option=com_installer&view=update');
    cy.clickToolbarButton('purge-cache');
    cy.checkForSystemMessage('Cache purged');

    cy.visit('administrator/index.php?option=com_installer&view=update');
    cy.clickToolbarButton('find-updates');
    cy.checkForSystemMessage('Finished refreshing extension update sites');

    cy.searchForItem(PACKAGE_ELEMENT);
    cy.get('table tbody tr').contains(PACKAGE_ELEMENT).parents('tr')
      .should('contain', FAKE_VERSION);

    // ------------------------------------------------------------------
    // 7. Applica l'update del package tramite il flusso reale di Joomla
    // ------------------------------------------------------------------
    cy.checkAllResults();
    cy.clickToolbarButton('update');
    cy.get('#system-message-container').should('contain', 'successfully updated');

    // ------------------------------------------------------------------
    // 8. Verifica che il package sia aggiornato E che MagicLogin abbia
    //    mantenuto la configurazione
    // ------------------------------------------------------------------
    cy.visit('administrator/index.php?option=com_plugins&view=plugins');
    cy.searchForItem(PLUGIN_NAME);
    cy.get('tbody tr').contains(PLUGIN_NAME).parents('tr')
      .find('.badge-success')
      .should('exist');
  });
});
