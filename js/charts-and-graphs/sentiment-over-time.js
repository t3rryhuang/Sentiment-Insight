// js/charts/sentiment-over-time.js

(function(){
    // Global variables
    var chart;
    var currentMode = 'breakdown';
    var preparedData = [];
  
    // Expose the setup function to the global scope.
    window.setupSentimentChart = function(sentimentData, setID, isLoggedIn) {
        // Prepare the data array
        preparedData = sentimentData.map(function(item){
            return {
                date: new Date(item.date),
                Positive: parseInt(item.Positive, 10) || 0,
                Neutral: parseInt(item.Neutral, 10) || 0,
                Negative: parseInt(item.Negative, 10) || 0
            };
        });
        
        // Get chart context
        var ctx = document.getElementById('sentimentTimeSeriesChart').getContext('2d');
        
        // Register the annotation plugin (for Chart.js v3)
        Chart.register(window['chartjs-plugin-annotation']);
        
        // Create initial Breakdown chart
        chart = createBreakdownChart(ctx);
        
        // Setup event listeners for controls
        setupControls(setID, isLoggedIn);
    };
  
    function commonChartOptions() {
        return {
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                annotation: {
                    annotations: {
                        todayLine: {
                            type: 'line',
                            scaleID: 'x',
                            // Set to current time
                            value: new Date().toISOString(),
                            borderColor: 'blue', // UPDATED: Change color to blue
                            borderWidth: 2,
                            borderDash: [6, 6],
                            label: {
                                enabled: true,
                                content: 'Today',
                                position: 'center'
                            }
                        }
                    }
                }
            }
            ,
            scales: {
                x: {
                    type: 'time',
                    time: {
                        unit: 'day',
                        tooltipFormat: 'MM/dd/yyyy'
                    },
                    title: {
                        display: true,
                        text: 'Date'
                    },
                    stacked: currentMode === 'breakdown',
                    grid: {
                        display: true,
                        color: 'rgba(200,200,200,0.2)'
                    }
                },
                y: {
                    beginAtZero: true,
                    stacked: currentMode === 'breakdown',
                    title: {
                        display: true,
                        text: currentMode === 'breakdown' ? 'Number of Posts' : 'Average Severity'
                    },
                    grid: {
                        display: true,
                        color: 'rgba(200,200,200,0.2)'
                    }
                }
            },
            interaction: {
                mode: 'index',
                intersect: false
            },
            hover: {
                mode: 'nearest',
                intersect: true
            }
        };
    }
  
    function createBreakdownChart(ctx) {
        var config = {
            type: 'line',
            data: {
                labels: preparedData.map(function(d){ return d.date; }),
                datasets: [
                    {
                        label: 'Negative',
                        data: preparedData.map(function(d){ return d.Negative; }),
                        borderColor: '#721817',
                        backgroundColor: 'rgba(114,24,23,0.1)',
                        fill: true,
                        stack: 'combined'
                    },
                    {
                        label: 'Neutral',
                        data: preparedData.map(function(d){ return d.Neutral; }),
                        borderColor: '#fa9f42',
                        backgroundColor: 'rgba(250,159,66,0.1)',
                        fill: true,
                        stack: 'combined'
                    },
                    {
                        label: 'Positive',
                        data: preparedData.map(function(d){ return d.Positive; }),
                        borderColor: '#0B6E4F',
                        backgroundColor: 'rgba(11,110,79,0.1)',
                        fill: true,
                        stack: 'combined'
                    }
                ]
            },
            options: commonChartOptions()
        };
        return new Chart(ctx, config);
    }
  
    function createAverageChart(ctx) {
        var avgData = preparedData.map(function(d){
            var total = d.Positive + d.Neutral + d.Negative;
            return total === 0 ? null : ((d.Positive * 4) + (d.Neutral * 5) + (d.Negative * 6)) / total;
        });
        var config = {
            type: 'line',
            data: {
                labels: preparedData.map(function(d){ return d.date; }),
                datasets: [
                    {
                        label: 'Average Severity',
                        data: avgData,
                        borderColor: '#6A7FDB',
                        backgroundColor: 'rgba(106,127,219,0.1)',
                        fill: false,
                        pointStyle: 'circle',
                        pointRadius: 5,
                        pointBackgroundColor: '#6A7FDB'
                    }
                ]
            },
            options: commonChartOptions()
        };
        return new Chart(ctx, config);
    }
  
    function setupControls(setID, isLoggedIn) {
        // Graph mode toggle buttons
        document.getElementById('breakdownOption').addEventListener('click', function(){
            if(currentMode !== 'breakdown'){
                currentMode = 'breakdown';
                setActiveGraphOption();
                chart.destroy();
                var ctx = document.getElementById('sentimentTimeSeriesChart').getContext('2d');
                chart = createBreakdownChart(ctx);
            }
        });
        document.getElementById('averageOption').addEventListener('click', function(){
            if(currentMode !== 'average'){
                currentMode = 'average';
                setActiveGraphOption();
                chart.destroy();
                var ctx = document.getElementById('sentimentTimeSeriesChart').getContext('2d');
                chart = createAverageChart(ctx);
            }
        });
        
        function setActiveGraphOption(){
            var breakdownBtn = document.getElementById('breakdownOption');
            var averageBtn = document.getElementById('averageOption');
            var breakdownIcon = document.getElementById('breakdownIcon');
            var averageIcon = document.getElementById('averageIcon');
            if(currentMode === 'breakdown'){
                breakdownBtn.style.backgroundColor = "#D3D3D3";
                breakdownIcon.src = "images/icons/breakdown-black.svg";
                averageBtn.style.backgroundColor = "transparent";
                averageIcon.src = "images/icons/average-severity-grey.svg";
            } else {
                averageBtn.style.backgroundColor = "#D3D3D3";
                averageIcon.src = "images/icons/average-severity-black.svg";
                breakdownBtn.style.backgroundColor = "transparent";
                breakdownIcon.src = "images/icons/breakdown-grey.svg";
            }
        }
        
        // Time-range buttons
        function clearTimeRangeSelection(){
            document.querySelectorAll('.time-range-button').forEach(function(btn){
                btn.classList.remove('selected');
            });
        }
        document.getElementById('timeRange1W').addEventListener('click', function(){
            clearTimeRangeSelection();
            this.classList.add('selected');
            setTimeRange(7);
        });
        document.getElementById('timeRange1M').addEventListener('click', function(){
            clearTimeRangeSelection();
            this.classList.add('selected');
            setTimeRange(30);
        });
        document.getElementById('timeRangeYTD').addEventListener('click', function(){
            clearTimeRangeSelection();
            this.classList.add('selected');
            var now = new Date();
            setTimeRangeFrom(new Date(now.getFullYear(), 0, 1), now);
        });
        
        function setTimeRange(days){
            var now = new Date();
            var past = new Date();
            past.setDate(now.getDate() - days);
            updateTimeScale(past, now);
        }
        
        function setTimeRangeFrom(min, max){
            updateTimeScale(min, max);
        }
        
        // Scroll controls
        document.getElementById('scrollLeft').addEventListener('click', function(){
            scrollTimeScale(-7);
        });
        document.getElementById('scrollRight').addEventListener('click', function(){
            scrollTimeScale(7);
        });
        
        // Today button: scroll so that today is the rightmost end
        document.getElementById('todayButton').addEventListener('click', function(){
            var now = new Date();
            var currentMin = chart.options.scales.x.min ? new Date(chart.options.scales.x.min) : preparedData[0].date;
            var currentMax = chart.options.scales.x.max ? new Date(chart.options.scales.x.max) : preparedData[preparedData.length-1].date;
            var rangeWidth = currentMax - currentMin;
            updateTimeScale(new Date(now.getTime() - rangeWidth), now);
            chart.options.plugins.annotation.annotations.todayLine.value = now.toISOString();
            chart.update();
        });
        
        function updateTimeScale(min, max){
            chart.options.scales.x.min = min;
            chart.options.scales.x.max = max;
            chart.options.plugins.annotation.annotations.todayLine.value = new Date().toISOString();
            chart.update();
        }
        
        function scrollTimeScale(daysOffset){
            var min = chart.options.scales.x.min ? new Date(chart.options.scales.x.min) : preparedData[0].date;
            var max = chart.options.scales.x.max ? new Date(chart.options.scales.x.max) : preparedData[preparedData.length-1].date;
            var offsetMs = daysOffset * 24 * 60 * 60 * 1000;
            updateTimeScale(new Date(min.getTime() + offsetMs), new Date(max.getTime() + offsetMs));
        }
        
        // Save/Unsave button functionality
        var saveButton = document.getElementById('saveButton');
        saveButton.addEventListener('click', function(){
            if(!isLoggedIn){
                window.location.href = 'sign-in-page.php';
                return;
            }
            fetch('save-set.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'setID=' + encodeURIComponent(setID)
            })
            .then(response => response.json())
            .then(data => {
                if(data.success){
                    if(data.saved){
                        saveButton.classList.add('saved');
                        saveButton.innerHTML = '<img src="images/icons/saved.svg" alt="Saved Icon"> Saved';
                    } else {
                        saveButton.classList.remove('saved');
                        saveButton.innerHTML = '<img src="images/icons/save-grey.svg" alt="Save Icon"> Save';
                    }
                } else {
                    alert(data.message || 'Error toggling save.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while toggling save.');
            });
        });
    }
  })();
  