/**
 * @copyright  Copyright (C) 2021 Alikon. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

((Joomla, document) => {

    document.addEventListener('DOMContentLoaded', () => {
        //
        var colors = [
            '#FF0019',
            '#008FFB',
            '#00E396',
            '#e8e80c',
            '#FF4560',
            '#775DD0',
            '#546E7A',
            '#26a69a',
            
        ];
        var options = {
            series: [],
            chart: {
                height: 350,
                type: 'bar',
            },
            colors: colors,
            plotOptions: {
                bar: {
                    columnWidth: '45%',
                    distributed: true,
                }
            },
            dataLabels: {
                enabled: false
            },
            title: {
                text: 'Joomla distribution',
            },
            noData: {
                text: 'Loading...'
            },
            xaxis: {
                categories: [
                    'Joomla 3.X',
                    'Joomla 4.X',
                    'Joomla 5.X',
                    'Joomla 6.X',
                ],
                labels: {
                    style: {
                        colors: colors,
                        fontSize: '18px'
                    }
                }
            }
        };
        var options2 = {
            series: [],
            chart: {
                height: 350,
                type: 'bar',
            },
            colors: colors,
            plotOptions: {
                bar: {
                    columnWidth: '45%',
                    distributed: true,
                }
            },
            dataLabels: {
                enabled: false
            },
            title: {
                text: 'PHP distribution',
            },
            noData: {
                text: 'Loading...'
            },
            xaxis: {
                categories: [
                    'PHP 5.X',
                    'PHP 7.X',
                    'PHP 8.X',
                ],
                labels: {
                    style: {
                        colors: colors,
                        fontSize: '18px'
                    }
                }
            }
        };

        var options3 = {
            series: [],
            chart: {
                height: 350,
                type: 'bar',
            },
            colors: colors,
            plotOptions: {
                bar: {
                    columnWidth: '45%',
                    distributed: true,
                }
            },
            dataLabels: {
                enabled: false
            },
            title: {
                text: 'DB distribution',
            },
            noData: {
                text: 'Loading...'
            },
            xaxis: {
                categories: [
                    'MySQL',
                    'PostgreSQL',
                    'Mysqli/PDO',
                ],
                labels: {
                    style: {
                        colors: colors,
                        fontSize: '18px'
                    }
                }
            }
        };
    
        var chart = new ApexCharts(document.querySelector("#chart"), options);
        var chart2 = new ApexCharts(document.querySelector("#chart"), options2);
        var chart3 = new ApexCharts(document.querySelector("#chart"), options3);
        const timeElement = document.querySelector(".ice-cream");
        const seriesElement = document.querySelector(".series");
        updateQuote(chart, chart2, chart3, 360, seriesElement.value);
        chart.render();


        const result = document.querySelector(".result");
        timeElement.addEventListener("change", (event) => {
            //console.log('EVENT', timeElement.options[timeElement.selectedIndex].text)
            result.textContent = "Timeframe: " + timeElement.options[timeElement.selectedIndex].text;
            updateQuote(chart, chart2, chart3, event.target.value, seriesElement.value);
        });
        seriesElement.addEventListener("change", (event) => {
            //result.textContent = "You like" + event.target.value;
            updateQuote(chart, chart2, chart3, timeElement.value, event.target.value);
        });
        //
        async function updateQuote(chart, chart2, chart3, timeframe, series) {
            
            const response = await fetch("https://developer.joomla.org/stats/" + series + "?timeframe=" + timeframe);
            const data = await response.json();
            console.log('series', series);
            console.log('timeframe', timeframe);
            if (series == 'cms_version') {
                console.log('data',data.data.cms_version);
                dataFilter = data.data.cms_version;
                cmsVersion(dataFilter, chart)
            } 
            if (series == 'php_version') {
                console.log('data',data.data.php_version);
                chart2.render();
                phpVersion(data.data.php_version, chart2)
            }
            if (series == 'db_type') {
                console.log('data',data.data.db_type);
                chart3.render();
                db_type(data.data.db_type, chart3)
            }
        }
        //
        function phpVersion(dataFilter, chart) {
            let ver5 = 0;
            let ver7 = 0;
            let ver8 = 0;
            for (var i in dataFilter) {
                //console.log('i', i);
                switch (i) {
                    case '5.6':
                    case '5.5':
                    case '5.4':
                    case '5.3':
                        ver5 += dataFilter[i]
                        break
                    case '7.0':
                    case '7.1':
                    case '7.2':
                    case '7.3':
                    case '7.4':
                        ver7 += dataFilter[i]
                        break
                    case '8.0':
                    case '8.1':
                    case '8.2':
                    case '8.3':
                    case '8.4':
                    case '8.5':
                        ver8 += dataFilter[i]
                        break
                }
            }
            console.log('8', ver8);
            console.log('7', ver7);
            console.log('5', ver5);
            chart.updateSeries([{
                name: 'Used',
                data: [ver5.toFixed(2), ver7.toFixed(2), ver8.toFixed(2)]
            }])
        }

        function cmsVersion(dataFilter, chart) {
            delete dataFilter["3.0"];
            delete dataFilter["3.1"];
            delete dataFilter["3.2"];
            delete dataFilter["3.3"];

            let ver3 = 0;
            let ver4 = 0;
            let ver5 = 0;
            let ver6 = 0;

            for (var i in dataFilter) {
                //console.log('i', i);
                switch (i) {
                    case '3.10':
                    case '3.9':
                    case '3.8':
                    case '3.7':
                    case '3.6':
                    case '3.5':
                    case '3.4':
                        ver3 += dataFilter[i]
                        break
                    case '4.0':
                    case '4.1':
                    case '4.2':
                    case '4.3':
                    case '4.4':
                    case '4.5':
                        ver4 += dataFilter[i]
                        break
                    case '5.0':
                    case '5.1':
                    case '5.2':
                    case '5.3':
                    case '5.4':
                        ver5 += dataFilter[i]
                        break
                    case '6.0':
                    case '6.1':
                        ver6 += dataFilter[i]
                        break
                }
            }
            console.log('3', ver3);
            console.log('4', ver4);
            console.log('5', ver5);
            chart.updateSeries([{
                name: 'Used',
                data: [ver3.toFixed(2), ver4.toFixed(2), ver5.toFixed(2), ver6.toFixed(2)]
            }])
        }
        //
        function db_type(dataFilter, chart) {
            let mysql = 0;
            let pgsql = 0;
            let mysqli = 0;
            for (var i in dataFilter) {
                //console.log('i', i);
                switch (i) {
                    case 'mysql':
                        mysql += dataFilter[i]
                        break
                    case 'pgsql':
                    case 'postgresql':
                        pgsql += dataFilter[i]
                        break
                    case 'mysqli':
                    case 'pdomysql':
                        mysqli += dataFilter[i]
                        break
                }
            }
            console.log('mysqli', mysqli);
            console.log('postgres', pgsql);
            console.log('mysql', mysql);
            chart.updateSeries([{
                name: 'Used',
                data: [mysql.toFixed(2), ,mysqli.toFixed(2), pgsql.toFixed(2)]
            }])
        }
        //
    });

})(window.Joomla, document);