// js/charts-and-graphs/sankey-diagram.js

(function(){
    // We'll expose one function for external usage
    window.setupSankeyDiagram = function(sankeyData) {
        
        // Build the Sankey trace
        var sankeyTrace = {
            type: 'sankey',
            orientation: 'h',
            node: {
                pad: 15,
                thickness: 20,
                line: {
                    color: 'transparent',
                    width: 0
                },
                label: sankeyData.node.label,
                color: sankeyData.node.color,
                textfont: {
                    color: '#FFFFFF',
                    size: 14
                }
            },
            link: {
                source: sankeyData.link.source,
                target: sankeyData.link.target,
                value: sankeyData.link.value,
                color: sankeyData.link.color
            }
        };

        // The layout object for Plotly
        var layout = {
            font: {
                size: 14,
                color: '#fff'
            },
            paper_bgcolor: '#101214',
            plot_bgcolor: '#101214',
            height: 700
        };

        // Render it into the 'sankey-plot' div
        Plotly.newPlot('sankey-plot', [sankeyTrace], layout);
    };
})();
