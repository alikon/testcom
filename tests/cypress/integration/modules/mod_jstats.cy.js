describe('Test in frontend that the Jstats', () => {
  it('can display joomla statistics', () => {
    cy.db_createModule({ title: 'Joomla! Statistics', module: 'mod_jstats' })
      .then(() => {
        cy.visit('/');
        cy.contains('Joomla! Statistics');
      });
  });
});
