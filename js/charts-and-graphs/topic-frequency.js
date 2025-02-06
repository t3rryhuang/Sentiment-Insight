// Global reference to the chart instance so we can destroy/recreate it on filter changes
let freqChartInstance = null;

/**
 * setupTopicFrequencyChart
 * 
 * @param {Array} allData - The full array of topics; each object contains:
 *        { topic: string, avgSeverity: number, totalImpressions: number, percentageOfTotal: number }
 * @param {String} defaultFilter - One of "negative", "neutral", or "positive" to select initially.
 */
function setupTopicFrequencyChart(allData, defaultFilter) {
    // Get references to the filter buttons
    const negBtn = document.getElementById('topicNegBtn');
    const neuBtn = document.getElementById('topicNeuBtn');
    const posBtn = document.getElementById('topicPosBtn');
    
    // Get reference to the table body for the top 5 topics
    const tableBody = document.getElementById('topicFreqTableBody');

    // Filter data by severity category.
    function filterBySeverity(category) {
        if (category === 'negative') {
            // Only topics with avgSeverity > 5.0
            return allData.filter(item => item.avgSeverity > 5.0);
        } else if (category === 'neutral') {
            // Only topics with avgSeverity exactly equal to 5.0
            return allData.filter(item => item.avgSeverity === 5.0);
        } else if (category === 'positive') {
            // Only topics with avgSeverity < 5.0
            return allData.filter(item => item.avgSeverity < 5.0);
        }
        return [];
    }

    // Determine the bar color based on severity.
    function getBarColor(item) {
        if (item.avgSeverity > 5.0) return '#721817'; // Red for negative
        if (item.avgSeverity < 5.0) return '#0B6E4F'; // Green for positive
        return '#FA9F42'; // Orange for neutral (exactly 5)
    }

    // Update both the chart and the table based on the chosen filter.
    function updateChartAndTable(category) {
        // 1) Filter data for the given category.
        let filtered = filterBySeverity(category);

        // 2) Sort the filtered data by totalImpressions descending.
        filtered.sort((a, b) => b.totalImpressions - a.totalImpressions);

        // 3) Compute the overall sum of impressions for the filtered data (all posts matching filter).
        let overallSum = filtered.reduce((acc, cur) => acc + cur.totalImpressions, 0);

        // 4) Slice the top 5 items (or fewer if less than 5 exist).
        let top5 = filtered.slice(0, 5);

        // 5) Build arrays for the chart.
        let labels = [];
        let barData = [];
        let lineData = [];
        let cumulative = 0;

        top5.forEach(item => {
            labels.push(item.topic);
            barData.push(item.totalImpressions);
        });

        // Compute cumulative percentages relative to overallSum (of all filtered topics)
        top5.forEach(item => {
            cumulative += item.totalImpressions;
            // Calculate cumulative percentage relative to overallSum (not just the top 5)
            let percent = overallSum > 0 ? (cumulative / overallSum) * 100 : 0;
            lineData.push(percent);
        });

        // 6) Determine bar colors for each item.
        let barColors = top5.map(item => getBarColor(item));

        // 7) Update the top-5 table.
        tableBody.innerHTML = '';
        top5.forEach(item => {
            let tr = document.createElement('tr');
            let tdTopic = document.createElement('td');
            tdTopic.textContent = item.topic;
            let tdImpressions = document.createElement('td');
            tdImpressions.textContent = item.totalImpressions;
            tr.appendChild(tdTopic);
            tr.appendChild(tdImpressions);
            tableBody.appendChild(tr);
        });

        // 8) Destroy any previous chart instance.
        const ctx = document.getElementById('topicFrequencyChart').getContext('2d');
        if (freqChartInstance) {
            freqChartInstance.destroy();
        }

        // 9) Create the new Chart.js instance.
        freqChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Frequency of Mentions',
                        data: barData,
                        borderColor: barColors,
                        backgroundColor: barColors,
                        yAxisID: 'y-axis-1',
                        order: 10  // Bars behind the line
                    },
                    {
                        label: 'Cumulative Percentage',
                        data: lineData,
                        type: 'line',
                        borderColor: '#ffffff',
                        backgroundColor: 'transparent',
                        fill: false,
                        yAxisID: 'y-axis-2',
                        order: 2,  // Draw the line on top of the bars
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        stacked: true,
                        title: {
                            display: true,
                            text: 'Topics',
                            color: '#FFFFFF'
                        },
                        ticks: {
                            color: '#FFFFFF',
                            maxRotation: 70,
                            minRotation: 0
                        },
                        grid: {
                            color: '#353638'
                        }
                    },
                    'y-axis-1': {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Frequency of Mentions',
                            color: '#FFFFFF'
                        },
                        ticks: {
                            color: '#FFFFFF'
                        },
                        grid: {
                            color: '#808080'
                        }
                    },
                    'y-axis-2': {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Cumulative Percentage',
                            color: '#FFFFFF'
                        },
                        ticks: {
                            // Remove percentage symbol
                            callback: function(value) {
                                return Math.round(value);  // Remove the % symbol
                            },
                            color: '#FFFFFF'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        labels: {
                            color: '#FFFFFF'
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                }
            }
        });
    }

    // Attach click events to the filter buttons.
    negBtn.addEventListener('click', function() {
        negBtn.classList.add('selected');
        neuBtn.classList.remove('selected');
        posBtn.classList.remove('selected');
        updateChartAndTable('negative');
    });
    neuBtn.addEventListener('click', function() {
        negBtn.classList.remove('selected');
        neuBtn.classList.add('selected');
        posBtn.classList.remove('selected');
        updateChartAndTable('neutral');
    });
    posBtn.addEventListener('click', function() {
        negBtn.classList.remove('selected');
        neuBtn.classList.remove('selected');
        posBtn.classList.add('selected');
        updateChartAndTable('positive');
    });

    // Run the default filter on load.
    updateChartAndTable(defaultFilter);
}
