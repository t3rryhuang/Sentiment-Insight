// js/charts-and-graphs/force-field-diagram.js

document.addEventListener('DOMContentLoaded', function() {

    /**
     * drawLimitedForceFieldDiagram
     * Renders the short version (e.g., top 5 negative & top 5 positive)
     * inside .analysis-section-force-field
     */
    window.drawLimitedForceFieldDiagram = function(data) {
        if (!data) return;

        // 1) Find container
        const container = document.querySelector(".analysis-section-force-field");
        if (!container) return;

        // Remove any old limited diagram SVG if it exists
        container.querySelectorAll("svg.force-field-limited").forEach(el => el.remove());

        // 2) Build the SVG
        const svgNS = "http://www.w3.org/2000/svg";
        const svgWidth = 1000;
        const svgHeight = 400;
        const centerX = 500;   // vertical center line
        const sideMargin = 50;
        const topMargin = 30;
        const gap = 40;

        // Gather all rows => find max difference from severity=5 => scaleFactor
        const allRows = [...(data.negative || []), ...(data.positive || [])];
        let maxDiff = 0;
        allRows.forEach(r => {
            const d = Math.abs(r.avgSeverity - 5);
            if (d > maxDiff) maxDiff = d;
        });
        const maxLeft  = centerX - sideMargin;
        const maxRight = (svgWidth - centerX) - sideMargin;
        const maxSpan  = Math.min(maxLeft, maxRight);
        const scaleFactor = maxDiff > 0 ? maxSpan / maxDiff : 0;

        // Create the SVG
        const svg = document.createElementNS(svgNS, "svg");
        svg.setAttribute("viewBox", `0 0 ${svgWidth} ${svgHeight}`);
        svg.setAttribute("width", "100%");
        svg.setAttribute("height", "400");
        svg.classList.add("force-field-limited");
        svg.style.background = "#101214";

        // Marker definitions
        const defs = document.createElementNS(svgNS, "defs");

        // Negative marker (red)
        const negMarker = document.createElementNS(svgNS, "marker");
        negMarker.setAttribute("id", "arrowNeg");
        negMarker.setAttribute("markerWidth", "7");
        negMarker.setAttribute("markerHeight", "5");
        negMarker.setAttribute("refX", "7");
        negMarker.setAttribute("refY", "2.5");
        negMarker.setAttribute("orient", "auto");
        const negPoly = document.createElementNS(svgNS,"polygon");
        negPoly.setAttribute("points","0 0, 7 2.5, 0 5");
        negPoly.setAttribute("fill","#721817");
        negMarker.appendChild(negPoly);
        defs.appendChild(negMarker);

        // Positive marker (green)
        const posMarker = document.createElementNS(svgNS, "marker");
        posMarker.setAttribute("id","arrowPos");
        posMarker.setAttribute("markerWidth","7");
        posMarker.setAttribute("markerHeight","5");
        posMarker.setAttribute("refX","7");
        posMarker.setAttribute("refY","2.5");
        posMarker.setAttribute("orient","auto");
        const posPoly = document.createElementNS(svgNS,"polygon");
        posPoly.setAttribute("points","0 0, 7 2.5, 0 5");
        posPoly.setAttribute("fill","#0B6E4F");
        posMarker.appendChild(posPoly);
        defs.appendChild(posMarker);

        svg.appendChild(defs);

        // Draw center line
        const centerLine = document.createElementNS(svgNS,"line");
        centerLine.setAttribute("x1", centerX);
        centerLine.setAttribute("y1", 0);
        centerLine.setAttribute("x2", centerX);
        centerLine.setAttribute("y2", svgHeight);
        centerLine.setAttribute("stroke","#fff");
        centerLine.setAttribute("stroke-width","2");
        svg.appendChild(centerLine);

        let yPos = topMargin;

        function drawLine(topic, severity, isPositive) {
            const diff = Math.abs(severity - 5);
            const arrowLen = diff * scaleFactor;
            const color = isPositive ? "#0B6E4F" : "#721817";
            const markerId = isPositive ? "arrowPos" : "arrowNeg";

            let xBase;
            if (isPositive) {
                // arrow from left => center
                xBase = centerX - arrowLen;
            } else {
                // arrow from right => center
                xBase = centerX + arrowLen;
            }

            const line = document.createElementNS(svgNS,"line");
            line.setAttribute("x1", xBase);
            line.setAttribute("y1", yPos);
            line.setAttribute("x2", centerX);
            line.setAttribute("y2", yPos);
            line.setAttribute("stroke", color);
            line.setAttribute("stroke-width","3");
            line.setAttribute("marker-end", `url(#${markerId})`);

            // Label above, offset from center line by 20px
            const text = document.createElementNS(svgNS,"text");
            text.setAttribute("fill","#fff");
            text.setAttribute("font-size","15");
            text.setAttribute("y", yPos - 6);

            if(isPositive) {
                text.setAttribute("x", centerX - 30);
                text.setAttribute("text-anchor","end");
            } else {
                text.setAttribute("x", centerX + 30);
                text.setAttribute("text-anchor","start");
            }
            text.textContent = topic;

            svg.appendChild(line);
            svg.appendChild(text);
            yPos += gap;
        }

        // Draw negative first
        (data.negative || []).forEach(row => {
            drawLine(row.topic, row.avgSeverity, false);
        });
        // Then positive
        (data.positive || []).forEach(row => {
            drawLine(row.topic, row.avgSeverity, true);
        });

        container.appendChild(svg);
    };


    window.drawFullForceFieldDiagram = function(fullData) {
        if (!fullData) return;
    
        const container = document.getElementById("svgContainer");
        if (!container) return;
    
        // Clear old diagram if any
        container.innerHTML = "";
    
        const svgNS = "http://www.w3.org/2000/svg";
        const svgWidth = 1000;
        const centerX = 500;
        const sideMargin = 50;
        const topMargin = 30;
    
        // Calculate height dynamically based on number of topics
        const numRows = (fullData.negative || []).length + (fullData.positive || []).length;
        const rowHeight = 36; // Adjust this value for spacing
        const minHeight = 600; // Minimum height to prevent excessive smallness
        const svgHeight = Math.max(minHeight, numRows * rowHeight);
    
        // Gather all for scaling
        const allRows = [...(fullData.negative || []), ...(fullData.positive || [])];
        let maxDiff = 0;
        allRows.forEach(r => {
            const d = Math.abs(r.avgSeverity - 5);
            if (d > maxDiff) maxDiff = d;
        });
        const maxLeft = centerX - sideMargin;
        const maxRight = (svgWidth - centerX) - sideMargin;
        const maxSpan = Math.min(maxLeft, maxRight);
        const scaleFactor = maxDiff > 0 ? maxSpan / maxDiff : 0;
    
        // Create SVG
        const svg = document.createElementNS(svgNS, "svg");
        svg.setAttribute("viewBox", `0 0 ${svgWidth} ${svgHeight}`);
        svg.setAttribute("width", "100%");
        svg.setAttribute("height", svgHeight);
        svg.style.background = "#222222";
        svg.style.display = "block";
        svg.style.width = "100vw";
        svg.style.height = svgHeight + "px";
    
        // Define arrow markers
        const defs = document.createElementNS(svgNS, "defs");
    
        function createArrowMarker(id, color) {
            const marker = document.createElementNS(svgNS, "marker");
            marker.setAttribute("id", id);
            marker.setAttribute("markerWidth", "7");
            marker.setAttribute("markerHeight", "5");
            marker.setAttribute("refX", "7");
            marker.setAttribute("refY", "2.5");
            marker.setAttribute("orient", "auto");
    
            const poly = document.createElementNS(svgNS, "polygon");
            poly.setAttribute("points", "0 0, 7 2.5, 0 5");
            poly.setAttribute("fill", color);
    
            marker.appendChild(poly);
            defs.appendChild(marker);
        }
    
        createArrowMarker("arrowNegFull", "#721817"); // Negative (red)
        createArrowMarker("arrowPosFull", "#0B6E4F"); // Positive (green)
    
        svg.appendChild(defs);
    
        // Draw center line
        const centerLine = document.createElementNS(svgNS, "line");
        centerLine.setAttribute("x1", centerX);
        centerLine.setAttribute("y1", 0);
        centerLine.setAttribute("x2", centerX);
        centerLine.setAttribute("y2", svgHeight);
        centerLine.setAttribute("stroke", "#fff");
        centerLine.setAttribute("stroke-width", "2");
        svg.appendChild(centerLine);
    
        // Adjust gap dynamically to prevent overcrowding
        const maxGap = 35;
        const minGap = 20;
        const gap = Math.max(minGap, Math.min(maxGap, svgHeight / numRows));
    
        let yPos = topMargin;
    
        function drawLine(topic, severity, isPositive) {
            const diff = Math.abs(severity - 5);
            const arrowLen = diff * scaleFactor;
            const color = isPositive ? "#0B6E4F" : "#721817";
            const markerId = isPositive ? "arrowPosFull" : "arrowNegFull";
    
            let xBase;
            if (isPositive) {
                xBase = centerX - arrowLen;
            } else {
                xBase = centerX + arrowLen;
            }
    
            const line = document.createElementNS(svgNS, "line");
            line.setAttribute("x1", xBase);
            line.setAttribute("y1", yPos);
            line.setAttribute("x2", centerX);
            line.setAttribute("y2", yPos);
            line.setAttribute("stroke", color);
            line.setAttribute("stroke-width", "3");
            line.setAttribute("marker-end", `url(#${markerId})`);
    
            const text = document.createElementNS(svgNS, "text");
            text.setAttribute("fill", "#fff");
            text.setAttribute("font-size", "14");
            text.setAttribute("y", yPos - 6);
    
            if (isPositive) {
                text.setAttribute("x", centerX - 30);
                text.setAttribute("text-anchor", "end");
            } else {
                text.setAttribute("x", centerX + 30);
                text.setAttribute("text-anchor", "start");
            }
            text.textContent = topic;
    
            svg.appendChild(line);
            svg.appendChild(text);
            yPos += gap;
        }
    
        // Draw negative first
        (fullData.negative || []).forEach(row => {
            drawLine(row.topic, row.avgSeverity, false);
        });
    
        // Then positive
        (fullData.positive || []).forEach(row => {
            drawLine(row.topic, row.avgSeverity, true);
        });
    
        container.appendChild(svg);
    };
    

    // On page load, automatically draw the limited diagram if present
    if (window.forceFieldData) {
        drawLimitedForceFieldDiagram(window.forceFieldData);
    }
});
