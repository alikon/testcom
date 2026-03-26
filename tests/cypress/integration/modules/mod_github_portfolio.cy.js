describe('Test in frontend that the Jstats', () => {
  it('can display Github Portfolio', () => {
    cy.db_createModule({ title: 'Github Portfolio', module: 'mod_github_portfolio' })
      .then(() => {
        cy.visit('/');
        cy.contains('Github Portfolio');
      });
  });
});
