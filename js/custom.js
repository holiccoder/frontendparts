$(document).ready(function(){
    $("#upper-half").resizable({
        alsoResizeReverse: "#down-half"
    });
    $("#down-left").resizable({
        alsoResizeReverse: "#down-middle"
    });
    $("#down-middle").resizable({
        alsoResizeReverse:"#down-left"
    });
    $("#down-middle").resizable({
        alsoResizeReverse:"#down-right"
    });
    $(".down-right").resizable({
        alsoResizeReverse: "#down-middle"
    });

    new PerfectScrollbar(".html");
    new PerfectScrollbar(".css");
    new PerfectScrollbar(".javascript");
    new PerfectScrollbar(".upper-half");
});