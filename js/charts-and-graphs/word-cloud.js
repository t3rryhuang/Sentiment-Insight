// js/charts-and-graphs/word-cloud.js

document.addEventListener('DOMContentLoaded', function(){
    const container = document.getElementById('wordCloudContainer');
    if (!container) return;
    
    // Use the full topic frequency data.
    // If not available, try the alternative variable.
    const topics = window.topicFreqDataAll || window.topicFreqData || [];
    if (topics.length === 0) {
        container.innerHTML = '<p>No topics data available.</p>';
        return;
    }
    
    // Determine the maximum impressions among topics for scaling.
    const maxImpressions = topics.reduce((max, item) => {
        return item.totalImpressions > max ? item.totalImpressions : max;
    }, 0);
    
    // Define min and max font sizes (in pixels)
    const minFontSize = 12;
    const maxFontSize = 40;
    
    // Create a document fragment to accumulate the word cloud elements.
    const fragment = document.createDocumentFragment();
    
    topics.forEach(item => {
        const span = document.createElement('span');
        span.textContent = item.topic + " ";
        
        // Calculate font size proportional to totalImpressions.
        let fontSize = minFontSize;
        if(maxImpressions > 0) {
            fontSize = minFontSize + (item.totalImpressions / maxImpressions) * (maxFontSize - minFontSize);
        }
        span.style.fontSize = Math.round(fontSize) + 'px';
        
        // Set color based on avgSeverity.
        if (item.avgSeverity > 5.0) {
            span.style.color = '#721817'; // negative: red
        } else if (item.avgSeverity < 5.0) {
            span.style.color = '#0B6E4F'; // positive: green
        } else {
            span.style.color = '#FA9F42'; // neutral: yellow/orange
        }
        
        // Add some margin for spacing.
        span.style.margin = '5px';
        
        fragment.appendChild(span);
    });
    
    // Clear container and append the word cloud.
    container.innerHTML = '';
    container.appendChild(fragment);
});
