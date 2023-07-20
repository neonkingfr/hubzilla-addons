var loglevel = 3; // 0 = errors/warnings, 1 = info, 2 = debug, 3 = dump data structures
var templateCheckbox = "";

function t() {
    var now = new Date();
    var dateString = now.toISOString();
    return dateString;
}

function createCheckboxes(list, after, belongsToExperimental) {
    list = list.reverse();
    var html = "";
    for (var i = 0; i < list.length; i++) {
        var name = list[i][0];
        var html = "";
        html = templateCheckbox.replaceAll("placeholdername", name);
        $(html).insertAfter(after);
        $("#id_" + name).removeAttr("checked");
    }
}

function fillGUI(config) {
    createCheckboxes(config.detectors, "#face_detectors", true);
    createCheckboxes(config.models, "#face_models", true);
    
    config["rm_names"] = [["names", false, false]]
    createCheckboxes(config.rm_names, "#face_names", true);
}

function requestConfig() {
    var i = window.location.pathname.lastIndexOf(window.location.pathname.split("/")[3]);
    var postURL = window.location.pathname.substr(0, i) + "config";
    ((loglevel >= 1) ? console.log(t() + " requesting configuration, url=" + postURL) : null);
    $.post(postURL, [], function (data) {
        ((loglevel >= 1) ? console.log(t() + " Server response: " + JSON.stringify(data)) : null);
        if (data['config']) {
            fillGUI(data['config']);
        } else {
            ((loglevel >= 0) ? console.log(t() + " Error: received no config from server ") : null);
        }
    },
            'json');
}

function setPostURL() {
    $("#face_form_remove").attr("action", window.location.pathname);
}

$(document).ready(function () {
    loglevel = parseInt($("#faces_log_level").text());
    ((loglevel >= 1) ? console.log(t() + " loglevel=" + loglevel) : null);
    templateCheckbox = $("#placeholdername_container").prop('outerHTML');
    $("#placeholdername_container").remove();
    setPostURL();
    requestConfig();
}
);

