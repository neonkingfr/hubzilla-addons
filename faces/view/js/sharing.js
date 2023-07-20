var loglevel = 3; // 0 = errors/warnings, 1 = info, 2 = debug, 3 = dump data structures

let url_addon = "";
let contacts;
let channel_name = "";
let files_shared = [];

let cmin = 0;
let cmax = -1;

function t() {
    var now = new Date();
    var dateString = now.toISOString();
    return dateString;
}

function requestConfig() {
    var i = window.location.pathname.lastIndexOf(window.location.pathname.split("/")[3]);
    var postURL = window.location.pathname.substr(0, i) + "config";
    ((loglevel >= 1) ? console.log(t() + " requesting configuration, url=" + postURL) : null);
    $.post(postURL, [], function (data) {
        ((loglevel >= 1) ? console.log(t() + " Server response: " + JSON.stringify(data)) : null);
        if (data['config']) {
            config = data['config'];
            cmax = config.closeness[0][1];
            $('#main-range').jRange('setValue', '0,' + cmax);
            for (let key in config) {
                if (key !== "closeness") {
                    delete config[key];
                }
            }
        } else {
            ((loglevel >= 0) ? console.log(t() + " Error: received no config from server ") : null);
        }
    },
            'json');
}

function setPostURL() {
    $("#face_form_sharing").attr("action", window.location.pathname);
}

function csliderUpdate() {
    if (cmin != 0) {
        $('#main-range').jRange('setValue', '0,' + cmax);
        return;
    }
    $('#main-range').attr('value', cmax);
    ((loglevel >= 1) ? console.log(t() + " Affinity set to - " + cmax) : null);
    postGetContacts(cmax);
}

function postGetContacts(closeness) {
    // clear
    contacts = {};
    files_shared = [];
    document.getElementById("faces-contact-list-share").textContent = "";
    document.getElementById("faces-you-share").textContent = "";
    document.getElementById("faces-shared-with-you").textContent = "";
    let postParams = {};
    if (closeness) {
        postParams = {closeness: closeness};
    }

    // get contacts
    let postURL = url_addon + "/contacts";
    ((loglevel >= 1) ? console.log(t() + " post start - requesting url = " + postURL) : null);

    $.post(postURL, postParams, function (data) {
        if (!data['status']) {
            ((loglevel >= 0) ? console.log(t() + " ERROR " + data['message']) : null);
            return;
        }
        ((loglevel >= 1) ? console.log(t() + " post " + postURL + " - received server message: " + data['message']) : null);
        ((loglevel >= 3) ? console.log(t() + " post " + postURL + " - received server data response: " + JSON.stringify(data)) : null);
        if (data['contacts']) {
            contacts = data['contacts'];
            let i;
            for (i in contacts) {
                let contact = contacts[i];
                files_shared.push(contact);
                if (contact[4]) {
                    // do not display/download shared face of own channel
                    continue
                }
                let html = "<br/><strong>" + contact[1] + " (" + contact[2] + ")</strong>";
                $("#faces-contact-list-share").append(html);
            }
            postDownloadSharedFaces();
        }
    },
            'json');
}

function postDownloadSharedFaces() {
    if (files_shared.length > 0) {
        let f = files_shared.shift();
        let hash = f[0].substring(0, 8);
        let url = f[3];
        ((loglevel >= 2) ? console.log(t() + " try to download shared faces, url=" + url) : null);
        $.ajax({
            type: "POST",
            url: url,
            data: {},
            contentType: "application/json",
            success: function (data) {
                ((loglevel >= 1) ? console.log(t() + " downloaded shared faces, url=" + url + ", status=" + data['status'] + ", message=" + data['message']) : null);
                ((loglevel >= 3) ? console.log(t() + " downloaded shared faces, received data=" + JSON.stringify(data)) : null);
                if (data["faces"]) {
                    if (f[4]) {
                        displayReceivedFaces(data["faces"], url, true);
                    } else {
                        displayReceivedFaces(data["faces"], url, false);
                        postSharedFaces(data["faces"], hash, url);
                    }
                }
                postDownloadSharedFaces();
            },
            error: function (data, status) {
                ((loglevel >= 1) ? console.log(t() + " failed to downloaded shared faces from url=" + url + ", got status=" + status) : null);
                postDownloadSharedFaces();
            }
        });
    } else {
        postCleanupSharedFaces();
    }
}

function postSharedFaces(sharedFaces, hash, url) {
    var postURL = url_addon + "/shared";
    ((loglevel >= 1) ? console.log(t() + " post shared faces downloaded from url = " + url) : null);
    ((loglevel >= 3) ? console.log(t() + " post shared faces = " + JSON.stringify(sharedFaces)) : null);

    let s = JSON.stringify(sharedFaces);

    $.post(postURL, {faces: s, sender: hash, url: url}, function (data) {
        ((loglevel >= 1) ? console.log(t() + " post shared faces - received response - post url=" + postURL + " from server after posting shared faces downloaded from url=" + url) : null);
        if (data['status']) {
            ((loglevel >= 1) ? console.log(t() + " post shared faces - receiced server response - ok - post url=" + postURL + ", for faces downloaded from url=" + url) : null);
        } else {
            ((loglevel >= 1) ? console.log(t() + " post shared faces - receiced server response - failed - post url " + postURL + ", for faces downloaded from url=" + url) : null);
        }
    },
            'json');
}

function postCleanupSharedFaces() {
    let postURL = url_addon + "/cleanupshared";
    ((loglevel >= 1) ? console.log(t() + " post start - requesting url = " + postURL) : null);

    $.post(postURL, {}, function (data) {
        ((loglevel >= 1) ? console.log(t() + " post " + postURL + " - received server " + data['status'] + ", message: " + data['message']) : null);
    },
            'json');
}

function displayReceivedFaces(faces, url, isMe) {
    let models = [];
    let detectors = [];
    let distinct = [];
    let distinct_and_contact = [];
    var i;
    for (i = 0; i < faces.name.length; i++) {
        if (!distinct.includes(faces.name[i])) {
            distinct.push(faces.name[i]);
            let displayName = replaceNameForXchan_hash(faces.name[i]);
            if (displayName) {
                distinct_and_contact.push(displayName);
            }
        }
    }
    for (i = 0; i < faces.model.length; i++) {
        if (!models.includes(faces.model[i])) {
            models.push(faces.model[i]);
        }
    }
    for (i = 0; i < faces.detector.length; i++) {
        if (!detectors.includes(faces.detector[i])) {
            detectors.push(faces.detector[i]);
        }
    }

    let link = url.replace("/faces/", "/cloud/");
    link = link.replace(/share$/g, "faces/share.json");

    let html = "<p>";
    html += "<a href='" + link + "'>" + link + "</a>";

    html += ", detectors: <strong>" + detectors.toString() + "</strong>";
    html += ", models: <strong>" + models.toString() + "</strong>";
    html += "<br/>";
    if (isMe) {
        html += faces.name.length + " sending > ";
        html += "<strong>" + distinct.length + "</strong> distinct";
        html += "<br/>";
        html += "faces: <strong>" + distinct_and_contact.toString() + "</strong>";
        html += "</p>";
        $("#faces-you-share").append(html);
    } else {
        html += faces.name.length + " received > ";
        html += distinct.length + " distinct > ";
        html += "<strong>" + distinct_and_contact.length + "</strong> in your contact list";
        html += "<br/>";
        html += "faces: <strong>" + distinct_and_contact.toString() + "</strong>";
        html += "</p>";
        $("#faces-shared-with-you").append(html);
    }
}

function replaceNameForXchan_hash(hash) {
    if (contacts[hash]) {
        let contact = contacts[hash];
        let contact_name = contact[1] + " (" + contact[2] + ")";
        return contact_name;
    }
    return false;
}

$(document).ready(function () {
    loglevel = parseInt($("#faces_log_level").text());
    ((loglevel >= 1) ? console.log(t() + " loglevel=" + loglevel) : null);
    setPostURL();

    $("#main-range").jRange({
        isRange: true,
        from: 0,
        to: 99,
        step: 1,
        scale: ['Me', '|', 'Family', '|', 'Friends', '|', 'Acquaintances', '|', 'All'],
        width: '100%',
        showLabels: false,
        onstatechange: function (v) {
            let carr = v.split(",");
            cmin = carr[0];
            cmax = carr[1];
            ((loglevel >= 2) ? console.log(t() + " Affinity set - onstatechange - " + v) : null);

        },
        onbarclicked: function () {
            ((loglevel >= 2) ? console.log(t() + " Affinity set - onbarclicked - " + $('#main-range').jRange('getValue')) : null);
            csliderUpdate();
        },
        ondragend: function () {
            ((loglevel >= 2) ? console.log(t() + " Affinity set - ondragend - " + $('#main-range').jRange('getValue')) : null);
            csliderUpdate();
        }
    });

    requestConfig();
    channel_name = window.location.pathname.split("/")[2];  // "/faces/nick/sharing"
    channel_name = channel_name.split("?")[0];
    url_addon = window.location.origin + "/" + window.location.pathname.split("/")[1] + "/" + channel_name;
    if (!window.location.pathname.endsWith("/sharing")) {
        return;
    }
    postGetContacts();
}
);

