document.addEventListener('DOMContentLoaded', function() {
    const options = Joomla.getOptions('plg_content_aigenerator');
    
    if (options && options.articleId) {
        fetchSummary(options);
    }
});

function fetchSummary(options) {
    const loadingElement = document.getElementById('ai-summary-loading-' + options.articleId);
    
    if (!loadingElement) return;
    
    const params = new URLSearchParams();
    params.append(options.token, '1');
    params.append('article_id', options.articleId);
    params.append('cache_id', options.cacheId);
    
    fetch(options.apiUrl + '&' + params.toString())
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data && data.summary) {
                // Create summary element
                const summaryDiv = document.createElement('div');
                summaryDiv.className = 'ai-summary alert alert-info';
                summaryDiv.innerHTML = '<div class="ai-summary-content">' + data.summary + '</div>';
                
                // Replace loading element with summary
                loadingElement.replaceWith(summaryDiv);
            } else {
                loadingElement.textContent = 'No summary available';
                loadingElement.style.color = '#dc3545';
            }
        })
        .catch(error => {
            console.error('Error fetching AI summary:', error);
            loadingElement.textContent = 'Error loading summary';
            loadingElement.style.color = '#dc3545';
        });
}