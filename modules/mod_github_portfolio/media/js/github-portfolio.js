document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-github-portfolio]').forEach((container) => {
    const githubUsername = container.dataset.githubUsername || 'alikon';
    const maxItems = parseInt(container.dataset.maxItems || '6', 10);
    const cacheTime = parseInt(container.dataset.cacheTime || String(15 * 60000), 10);

    const containerId = container.id || '';

    if (!containerId) {
      console.error('mod_github_portfolio: container needs an id.');
      return;
    }

    const cacheKey = `github_${githubUsername}_${maxItems}`;
    const cached = localStorage.getItem(cacheKey);
    const cacheTimestamp = localStorage.getItem(`${cacheKey}_time`);

    if (cached && cacheTimestamp && Date.now() - parseInt(cacheTimestamp, 10) < cacheTime) {
      try {
        renderData(JSON.parse(cached), container);
        return;
      } catch (e) {
        console.warn('mod_github_portfolio: failed to parse cached data, refetching.', e);
      }
    }

    const apiUrl = `https://api.github.com/search/issues?q=author:${encodeURIComponent(
      githubUsername
    )}+type:pr&sort=updated&order=desc&per_page=${maxItems}`;

    fetch(apiUrl)
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        try {
          localStorage.setItem(cacheKey, JSON.stringify(data));
          localStorage.setItem(`${cacheKey}_time`, String(Date.now()));
        } catch (e) {
          // localStorage might be full or disabled; just ignore
          console.warn('mod_github_portfolio: unable to store cache.', e);
        }
        renderData(data, container);
      })
      .catch((error) => {
        console.error('Error fetching GitHub data:', error);
        container.innerHTML = `<p class="text-center text-danger col-12">Unable to load Portfolio. API Error: ${error.message}.</p>`;
      });
  });

  function formatDate(isoString) {
    if (!isoString) return 'N/A';
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(isoString).toLocaleDateString('en-US', options);
  }

  function renderData(data, container) {
    const items = (data && data.items) || [];

    container.innerHTML = '';

    if (!items.length) {
      const p = document.createElement('p');
      p.className = 'text-center text-muted col-12';
      p.textContent = 'No Pull Requests found.';
      container.appendChild(p);
      return;
    }

    items.forEach((pr) => {
      const repoUrl = (pr.repository_url || '').replace(
        'https://api.github.com/repos',
        'https://github.com'
      );
      const repoName = (pr.repository_url || '').split('/').pop() || '';
      const title = pr.title || '';
      const link = pr.html_url || '#';

      let statusLabel = 'Under Review';
      let dateText = `Last Updated: ${formatDate(pr.updated_at)}`;

      if (pr.draft === true) {
        statusLabel = 'Draft';
      } else if (pr.state === 'closed') {
        statusLabel = 'Closed';
        dateText = `Closed on: ${formatDate(pr.closed_at)}`;
      }

      const col = document.createElement('div');
      col.className = 'col-lg-4 col-md-6 col-sm-12 mb-4';

      const card = document.createElement('div');
      card.className = 'p-4 rounded shadow h-100';
      card.style.backgroundColor = '#f8f9fa';
      card.style.border = '1px solid #dee2e6';

      const h4 = document.createElement('h4');
      h4.textContent = title;

      const statusP = document.createElement('p');
      statusP.className = 'small';
      const statusSpan = document.createElement('span');
      statusSpan.style.fontWeight = '600';
      statusSpan.innerHTML = '&#9679; Status: ';
      const statusTextNode = document.createTextNode(statusLabel);
      statusP.appendChild(statusSpan);
      statusP.appendChild(statusTextNode);

      const repoP = document.createElement('p');
      repoP.className = 'small';
      const repoLabelSpan = document.createElement('span');
      repoLabelSpan.textContent = 'Repository:';
      const repoSpace = document.createTextNode(' ');
      const repoA = document.createElement('a');
      repoA.href = repoUrl;
      repoA.target = '_blank';
      repoA.rel = 'noopener noreferrer';
      repoA.textContent = repoName;
      repoP.appendChild(repoLabelSpan);
      repoP.appendChild(repoSpace);
      repoP.appendChild(repoA);

      const dateP = document.createElement('p');
      dateP.className = 'small';
      const dateSpan = document.createElement('span');
      dateSpan.className = 'fw-bold';
      dateSpan.textContent = dateText;
      dateP.appendChild(dateSpan);

      const linkA = document.createElement('a');
      linkA.href = link;
      linkA.target = '_blank';
      linkA.rel = 'noopener noreferrer';
      linkA.className = 'btn btn-primary btn-sm mt-3';
      linkA.textContent = 'View PR on GitHub »';

      card.appendChild(h4);
      card.appendChild(statusP);
      card.appendChild(repoP);
      card.appendChild(dateP);
      card.appendChild(linkA);

      col.appendChild(card);
      container.appendChild(col);
    });
  }
});
