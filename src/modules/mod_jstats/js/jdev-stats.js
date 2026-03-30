/**
 * @copyright  Copyright (C) 2021 Alikon. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

((Joomla, document) => {

    document.addEventListener('DOMContentLoaded', () => {

        const colors = [
            '#FF0019',
            '#008FFB',
            '#00E396',
            '#e8e80c',
            '#FF4560',
            '#775DD0',
            '#546E7A',
            '#26a69a',
        ];

        const buildOptions = (title, categories) => ({
            series: [],
            chart: {
                height: 350,
                type: 'bar',
                animations: { enabled: true, speed: 300 },
            },
            colors,
            plotOptions: { bar: { columnWidth: '45%', distributed: true } },
            dataLabels: { enabled: false },
            title: { text: title },
            noData: { text: 'Loading…' },
            xaxis: {
                categories,
                labels: { style: { colors, fontSize: '18px' } },
            },
        });

        const endpointMeta = {
            cms_version: {
                title:      'Joomla distribution',
                categories: ['Joomla 3.X', 'Joomla 4.X', 'Joomla 5.X', 'Joomla 6.X'],
            },
            php_version: {
                title:      'PHP distribution',
                categories: ['PHP 5.X', 'PHP 7.X', 'PHP 8.X'],
            },
            db_type: {
                title:      'DB distribution',
                categories: ['MySQL', 'PostgreSQL', 'Mysqli / PDO MySQL'],
            },
        };

        // ── Aggregation helpers (module-level, not inside blocks) ────────────

        const phpVersion = (dataFilter) => {
            let ver5 = 0, ver7 = 0, ver8 = 0;
            for (const i in dataFilter) {
                switch (i) {
                    case '5.3': case '5.4': case '5.5': case '5.6':
                        ver5 += dataFilter[i]; break;
                    case '7.0': case '7.1': case '7.2': case '7.3': case '7.4':
                        ver7 += dataFilter[i]; break;
                    case '8.0': case '8.1': case '8.2': case '8.3': case '8.4': case '8.5':
                        ver8 += dataFilter[i]; break;
                }
            }
            return [ver5.toFixed(2), ver7.toFixed(2), ver8.toFixed(2)];
        };

        const cmsVersion = (dataFilter) => {
            ['3.0', '3.1', '3.2', '3.3'].forEach(v => delete dataFilter[v]);
            let ver3 = 0, ver4 = 0, ver5 = 0, ver6 = 0;
            for (const i in dataFilter) {
                switch (i) {
                    case '3.4': case '3.5': case '3.6': case '3.7':
                    case '3.8': case '3.9': case '3.10':
                        ver3 += dataFilter[i]; break;
                    case '4.0': case '4.1': case '4.2': case '4.3': case '4.4': case '4.5':
                        ver4 += dataFilter[i]; break;
                    case '5.0': case '5.1': case '5.2': case '5.3': case '5.4':
                        ver5 += dataFilter[i]; break;
                    case '6.0': case '6.1':
                        ver6 += dataFilter[i]; break;
                }
            }
            return [ver3.toFixed(2), ver4.toFixed(2), ver5.toFixed(2), ver6.toFixed(2)];
        };

        const dbType = (dataFilter) => {
            let mysql = 0, pgsql = 0, mysqli = 0;
            for (const i in dataFilter) {
                switch (i) {
                    case 'mysql':
                        mysql += dataFilter[i]; break;
                    case 'pgsql': case 'postgresql':
                        pgsql += dataFilter[i]; break;
                    case 'mysqli': case 'pdomysql':
                        mysqli += dataFilter[i]; break;
                }
            }
            return [mysql.toFixed(2), pgsql.toFixed(2), mysqli.toFixed(2)];
        };

        const aggregators = {
            cms_version: cmsVersion,
            php_version: phpVersion,
            db_type:     dbType,
        };

        // ── Per-module initialisation ────────────────────────────────────────

        document.querySelectorAll('.mod-jstats').forEach((wrapper) => {

            const moduleId      = wrapper.id.replace('mod-jstats-', '');
            const chartEl       = wrapper.querySelector('#chart-' + moduleId);
            const timeElement   = wrapper.querySelector('.ice-cream');
            const seriesElement = wrapper.querySelector('.series');
            const resultEl      = wrapper.querySelector('.result');

            if (!chartEl || !timeElement || !seriesElement) return;

            const initTimeframe = parseInt(wrapper.dataset.timeframe, 10) || 360;
            const initEndpoint  = wrapper.dataset.endpoint || 'cms_version';

            timeElement.value   = initTimeframe;
            seriesElement.value = initEndpoint;

            // In-memory cache: cache[timeframe + ':' + endpoint] = seriesDataArray
            const cache = {};

            const meta  = endpointMeta[initEndpoint] || endpointMeta.cms_version;
            const chart = new ApexCharts(chartEl, buildOptions(meta.title, meta.categories));
            chart.render();

            // ── Function assignment expressions (no function declarations in blocks) ──

            const applyToChart = (chart, series, seriesData) => {
                const { title, categories } = endpointMeta[series];
                chart.updateOptions({
                    title: { text: title },
                    xaxis: {
                        categories,
                        labels: { style: { colors, fontSize: '18px' } },
                    },
                }, false, false);
                chart.updateSeries([{ name: 'Used', data: seriesData }]);
            };

            const updateChart = async (chart, timeframe, series) => {
                const cacheKey = timeframe + ':' + series;

                if (cache[cacheKey]) {
                    applyToChart(chart, series, cache[cacheKey]);
                    return;
                }

                chart.updateOptions({ noData: { text: 'Loading…' } }, false, false);
                chart.updateSeries([]);

                try {
                    const response = await fetch(
                        'https://developer.joomla.org/stats/' + series + '?timeframe=' + timeframe
                    );

                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.status);
                    }

                    const data       = await response.json();
                    const aggregate  = aggregators[series];

                    if (!aggregate) return;

                    const seriesData    = aggregate(data.data[series]);
                    cache[cacheKey]     = seriesData;
                    applyToChart(chart, series, seriesData);

                } catch (error) {
                    console.error('mod_jstats fetch error:', error);
                    chart.updateOptions({ noData: { text: 'Error loading data.' } }, false, false);
                }
            };

            updateChart(chart, initTimeframe, initEndpoint);

            timeElement.addEventListener('change', (event) => {
                resultEl.textContent = 'Timeframe: ' + timeElement.options[timeElement.selectedIndex].text;
                updateChart(chart, event.target.value, seriesElement.value);
            });

            seriesElement.addEventListener('change', (event) => {
                updateChart(chart, timeElement.value, event.target.value);
            });

        });

    });

})(window.Joomla, document);
