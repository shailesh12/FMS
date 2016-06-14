//Regular pie chart example
$(document).ready(function () {

    //total trainee graph
    nv.addGraph(function () {
        var chart = nv.models.pieChart()
                .x(function (d) {
                    return d.label;
                })
                .y(function (d) {
                    return d.value;
                })
                .color(['#808080', '#77d8bd', '#8ec8f1', '#c6b8e1', '#f3f3f3', '#ffbbbb'])
                .showLabels(true)
                .labelThreshold(.50)  //Configure the minimum slice size for labels to show up
                .labelType("value") //Configure what type of data to show in the label. Can be "key", "value" or "percent"
                .donut(true)          //Turn on Donut mode. Makes pie chart look tasty!
                .donutRatio(1.04)     //Configure how big you want the donut hole size to be.
                ;
        chart.valueFormat(d3.format('d'));
//        d3.select("#chart .nvd3-svg.nvd3.nv-wrap.nv-pieChart")
//                .attr("transform", "translate(0,0)");


        d3.select("#chart svg")
                .datum(totalpurchasedata)
                .transition().duration(1200)
                .call(chart);




        return chart;
    });
});