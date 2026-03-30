describe('Test in frontend that the Changelog module', () => {
  it('can display changelogs', () => {
    cy.db_createModule({ title: 'Changelog', module: 'mod_changelog' })
      .then(() => {
        cy.visit('/');
        cy.contains('Changelog');
      });
  });
});
