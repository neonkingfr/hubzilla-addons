var loglevel = 3; // 0 = errors/warnings, 1 = info, 2 = debug, 3 = dump data structures

function t() {
    var now = new Date();
    var dateString = now.toISOString();
    return dateString;
}
function setPostURL() {
    $("#face_form_probe").attr("action", window.location.pathname);
}

function correctLinks() {
    $('.link_correction').each(function (i, obj) {
        path = window.location.pathname;
        path = path.substr(0,path.lastIndexOf("/probe"));
        channel = path.substr(path.lastIndexOf("/") + 1);
        link = obj.href;
        link = link.replace("channel-nick", channel);
        obj.href = link;
        ((loglevel >= 1) ? console.log(t() + " link nr=" + i) : null);
    });
}

$(document).ready(function () {
    loglevel = parseInt($("#faces_log_level").text());
    ((loglevel >= 1) ? console.log(t() + " loglevel=" + loglevel) : null);
    setPostURL();
    correctLinks();
}
);

