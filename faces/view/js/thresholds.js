var loglevel = 3; // 0 = errors/warnings, 1 = info, 2 = debug, 3 = dump data structures
var defaults = [];

function t() {
    var now = new Date();
    var dateString = now.toISOString();
    return dateString;
}

function setDefaults() {
    $("#table_faces_thresholds").remove();
    fillGUI(defaults);
}

function createRow(model, model_thresholds) {
    html = "";
    html += "    <tr>";
    html += "        <td>" + model + "</td>";

    Object.keys(model_thresholds).forEach(function (key) {
        metric = key;
        value = model_thresholds[key];
        html += "        <td>";
        // input------------
        html += "<input ";
        html += "id='" + model + "_" + key + "' ";
        html += "name='" + model + "_" + key + "' ";
        html += "value='" + value + "' ";
        html += "type='text' ";
        html += "class='form-control'>";
        // --------------input  
        html += "</td>";
    });
    html += "    </tr>";
    return html;
}

function fillGUI(thresholds) {
    html = "";

    //html += "<table>";
    html += "    <tr>";
    html += "        <th>model</th>";
    html += "        <th>cosine</th>";
    html += "        <th>euclidean</th>";
    html += "       <th>euclidean_l2</th>";
    Object.keys(thresholds).forEach(function (key) {
        html += createRow(key, thresholds[key]);
    });

    html += "    </tr>";
    //html += "</table>";
    let table = document.createElement('table');
    table.innerHTML = html;
    table.id = "table_faces_thresholds";
    //table.width = "100%";
    referenceNode = document.getElementById("faces_thresholds_explain");
    referenceNode.parentNode.insertBefore(table, referenceNode.nextSibling);
}

function requestConfig() {
    var i = window.location.pathname.lastIndexOf(window.location.pathname.split("/")[3]);
    var postURL = window.location.pathname.substr(0, i) + "rthresholds";
    ((loglevel >= 1) ? console.log(t() + " requesting thresholds, url=" + postURL) : null);
    $.post(postURL, [], function (data) {
        ((loglevel >= 1) ? console.log(t() + " Server response: " + JSON.stringify(data)) : null);
        if (data['thresholds']) {
            defaults = data['defaults'];
            fillGUI(data['thresholds']);
        } else {
            ((loglevel >= 0) ? console.log(t() + " Error: received no thresholds from server ") : null);
        }
    },
            'json');
}

function setPostURL() {
    $("#face_form_thresholds").attr("action", window.location.pathname);
}

$(document).ready(function () {
    loglevel = parseInt($("#faces_log_level").text());
    ((loglevel >= 1) ? console.log(t() + " loglevel=" + loglevel) : null);
    setPostURL();
    requestConfig();
}
);

