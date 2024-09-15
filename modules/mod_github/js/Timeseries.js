/**
 * @copyright  Copyright (C) 2021 Alikon. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

((Joomla, document) => {

    document.addEventListener('DOMContentLoaded', () => {
        //
        const githubData = window.Joomla.getOptions('a-export');
        console.log('git',githubData['gitdata'].length);
        console.log('elm',githubData['gitdata'][0]);
        console.log('perc',githubData['percenti']);
        console.log('perc',githubData['percentp']);
        
        var issues = [];
        var prs = [];

        for (var i = 0; i < githubData['gitdata'].length; i++) {
            //ts2 = ts2 + 86400000;
            //console.log('sing', gitData['openissue'][i].date)
            //var innerArr = [dataSeries[0][i].date, dataSeries[0][i].value];
            var innerArr = [githubData['gitdata'][i].date, githubData['gitdata'][i].value];
            issues.push(innerArr);
            var innerArr = [githubData['gitdata'][i].date, githubData['gitdata'][i].value2];
            prs.push(innerArr);
        }
        //console.log('H',dates);
        var options = {
            series: [{
                name: 'Open issue',
                data: issues
            }],
            colors: ['#FF0000'],
            chart: {
                type: 'area',
                stacked: false,
                height: 350,
                zoom: {
                    type: 'x',
                    enabled: true,
                    autoScaleYaxis: true
                },
                toolbar: {
                    autoSelected: 'zoom'
                }
            },
            dataLabels: {
                enabled: false
            },
            markers: {
                size: 0,
            },
            title: {
                text: 'Open Issue ' + githubData['percenti'] + '%',
                align: 'left'
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    inverseColors: false,
                    opacityFrom: 0.5,
                    opacityTo: 0,
                    stops: [0, 90, 100]
                },
            },
            yaxis: {
                labels: {
                    formatter: function (val) {
                        return (val ).toFixed(0);
                    },
                },
                title: {
                    text: 'Issues'
                },
            },
            xaxis: {
                type: 'datetime',
            },
            tooltip: {
                shared: false,
                y: {
                    formatter: function (val) {
                        return (val).toFixed(0)
                    }
                }
            }
        };

        var chart = new ApexCharts(document.querySelector("#chartissues"), options);
        chart.render();

        var options2 = {
            series: [{
                name: 'Open Pull',
                data: prs
            }],
            colors: ['#00FF00'],
            chart: {
                type: 'area',
                stacked: false,
                height: 350,
                zoom: {
                    type: 'x',
                    enabled: true,
                    autoScaleYaxis: true
                },
                toolbar: {
                    autoSelected: 'zoom'
                }
            },
            dataLabels: {
                enabled: false
            },
            markers: {
                size: 0,
            },
            title: {
                text: 'Open Pull ' + githubData['percentp'] + '%',
                align: 'left'
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    inverseColors: false,
                    opacityFrom: 0.5,
                    opacityTo: 0,
                    stops: [0, 90, 100]
                },
            },
            yaxis: {
                labels: {
                    formatter: function (val) {
                        return (val ).toFixed(0);
                    },
                },
                title: {
                    text: 'Pr\'s'
                },
            },
            xaxis: {
                type: 'datetime',
            },
            tooltip: {
                shared: false,
                y: {
                    formatter: function (val) {
                        return (val).toFixed(0)
                    }
                }
            }
        };
        var chart2 = new ApexCharts(document.querySelector("#chartprs"), options2);
        chart2.render();
        //
    });

})(window.Joomla, document);