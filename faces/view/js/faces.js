var zoom = 2;
var minZoomLoaded = 1;
var imageCounter = 0;
var loadedCountMaxMultiplier = 3;
var loadedCountMax = loadedCountMaxMultiplier * zoom;  // How many picture to load befor stopping. After the stop the user has to scroll down to load more images.
var loadedCount = 0;
var isFaceRecognitionRunning = false;
var isWaitingForInitialDownload = false;
var sortDirectionReverse = false;
var stopLoadingImages = false;
var blockScrolledToEnd = false;

var endPanel = null;

// TODO set log level by server
var loglevel = 3; // 0 = errors/warnings, 1 = info, 2 = debug, 3 = dump data structures

let me = false;
let url_addon = "";
let filesToLoad = {faces: [], names: []};
let images = [];
let channel_name = "";
var receivedFaceEncodings = [];
var receivedNames = [];
var contacts = [];
var picturesProcessedID = [];
var files_name = []; // async http post for jquery >= 1.8 seems to be not asynchronous anymore
var counter_files_name = 0;
var counter_files_name_waiting = 0;
var names_waiting = [];
var countDownloadedImages = 0;

let unsentNames = [];
let unsentNamesMe = [];

var server_procid = "";
var server_time = "";

var immediateSearch = false;
var sort_exif = false;
var sortDirectionReverse = false;

let python_is_blocked = false;

// faces of other channels
var files_shared = [];

function t() {
    var now = new Date();
    var dateString = now.toISOString();
    return dateString;
}

function clear() {
    filesToLoad = {faces: [], names: []};
    images = [];
    faces = [];
    receivedFaceEncodings = [];
    receivedNames = [];
    picturesProcessedID = [];
    countFilteredImages = images.length;
    files_name = [];
    counter_files_name = 0;
    counter_files_name_waiting = 0;
    names_waiting = [];
    countDownloadedImages = 0;
    $("#face-panel-pictures").empty();
}

$(".collapseit").click(function () {
    ((loglevel >= 2) ? console.log(t() + "remove options shown") : null);
    var content = this.nextElementSibling;
    if (content.style.display === "block") {
        content.style.display = "none";
    } else {
        content.style.display = "block";
    }
    //document.getElementById("face-panel-config-remove").style.visibility = "visible"; 
});

function getMinWidth() {
    var w = $('#min_face_width').val();
    ((loglevel >= 2) ? console.log(t() + "min width=" + w) : null);
    return w;
}

function getLogLevel() {
    var logLevel = document.getElementById("faces-log-level").value;
    ((loglevel >= 2) ? console.log(t() + "logLevel=" + logLevel) : null);
    return logLevel;
}

function postConfigZoom(zoom) {
    let postURL = url_addon + "/zoom";
    ((loglevel >= 1) ? console.log(t() + " post zoom - requesting url = " + postURL) : null);
    let postParams = {zoom: zoom};
    $.post(postURL, postParams, function (data) {
        if (!data['status']) {
            ((loglevel >= 0) ? console.log(t() + " ERROR " + data['message']) : null);
            return;
        }
    },
            'json');
}

/*
 * This startst the face detection and recognition in the backend
 * 
 * @returns {undefined}
 */
function postDetectAndRecognize() {
    let postURL = url_addon + "/start";
    ((loglevel >= 1) ? console.log(t() + " post start - requesting url = " + postURL) : null);

    $.post(postURL, {}, function (data) {
        if (!data['status']) {
            ((loglevel >= 0) ? console.log(t() + " ERROR " + data['message']) : null);
            return;
        }
        ((loglevel >= 1) ? console.log(t() + " post start - received server message: " + data['message']) : null);
        ((loglevel >= 3) ? console.log(t() + " post start - received server data response: " + JSON.stringify(data)) : null);
        checkIfMe(data);
        if (data['contacts']) {
            contacts = data['contacts'];
            let i;
            for (i in contacts) {
                let contact = contacts[i];
                appendContact(contact);
                files_shared.push(contact);
            }
        }
        if (data['names']) {
            names_waiting = [];
            filesToLoad = {faces: data['names'], names: data['names_waiting']};
            downloadFaceData(data['names'], data['names_waiting']); // creates list of images
        }
        setConfig(data, true);
        if (!python_is_blocked) {
            waitForFinishedFaceDetection(10000);
        }
    },
            'json');
}
function postRecognize() {
    let postURL = url_addon + "/recognize";
    if (me) {
        postURL += "/me";
    }
    ((loglevel >= 1) ? console.log(t() + " post recognize - requesting url = " + postURL) : null);

    $.post(postURL, {}, function (data) {
        if (!data['status']) {
            ((loglevel >= 0) ? console.log(t() + " ERROR " + data['message']) : null);
            return;
        }
        ((loglevel >= 1) ? console.log(t() + " post recognize - received server message: " + data['message']) : null);
        ((loglevel >= 3) ? console.log(t() + " post recognize - received server data response: " + JSON.stringify(data)) : null);
        if (!python_is_blocked) {
            waitForFinishedFaceDetection(10000);
        }
    },
            'json');
}

/*
 * Download the results of the face recognition and show
 * 
 * @returns {undefined}
 */
function postDownloadResults() {
    var action = new Array();
    var postURL = url_addon + "/results";
    //clear();
    ((loglevel >= 1) ? console.log(t() + " post update - requesting results, url = " + postURL) : null);
    $.post(postURL, {action}, function (data) {
        if (!data['status']) {
            ((loglevel >= 0) ? console.log(t() + " ERROR " + data['message']) : null);
            return;
        }
        ((loglevel >= 1) ? console.log(t() + " post update - received results with server message: " + data['message']) : null);
        ((loglevel >= 3) ? console.log(t() + " post update - received results with server data response: " + JSON.stringify(data)) : null);
        if (data['names']) {
            checkIfMe(data);
            names_waiting = [];
            filesToLoad = {faces: data['names'], names: data['names_waiting']};
            downloadFaceData(data['names'], []); // creates list of images
            postDownloadSharedFaces();
        }
        setConfig(data, false);
    },
            'json');
}

function checkIfMe(data) {
    let splittees = window.location.pathname.split("/");
    if (splittees.length > 3) {
        let s = splittees[3];
        s = s.split("?")[0];
        if (s === "me") {
            // "/faces/nick/me"
            me = data['me'];  // = xchan_hash
            return;
        }
    }
    me = false;
}

function setConfig(data, is_start) {
    if (data['immediatly']) {
        immediateSearch = data['immediatly'];
        ((loglevel >= 1) ? console.log(t() + " set config data - immediatly=" + immediateSearch) : null);
    }
    if (data['sort_exif']) {
        sort_exif = data['sort_exif'];
        ((loglevel >= 1) ? console.log(t() + " set config data - sort_exif=" + sort_exif) : null);
    }
    if (data['sort_ascending']) {
        sortDirectionReverse = data['sort_ascending'];
        ((loglevel >= 1) ? console.log(t() + " set config data - sort_ascending=" + sortDirectionReverse) : null);
    }
    if (is_start) {
        if (data['zoom']) {
            zoom = data['zoom'];
            zoom = parseInt(zoom);
            ((loglevel >= 1) ? console.log(t() + " set config data - zoom=" + zoom) : null);
        }
    }
    if (data['python_blocked']) {
        python_is_blocked = true;
    } else {
        python_is_blocked = false;
    }
    ((loglevel >= 1) ? console.log(t() + " set config data - python_blocked=" + python_is_blocked) : null);
}
function downloadFaceData() {
    if (stopLoadingImages) {
        return;
    }
    isWaitingForInitialDownload = true;
    if (filesToLoad.names.length > 0) {
        // Read list of names.json per directory.
        // Why? names.json contains the names the user has set. If this file is not
        // empty the face recognition has not processed these names and faces.json
        // reflect an outdated status (names). To avoid "wrong" (old) names displayed
        // to the user: read the recent name changes from names.json an use the names
        // in there.
        if (filesToLoad.names.length > 0) {
            let f = filesToLoad.names.shift();
            ((loglevel >= 2) ? console.log(t() + " load face data - waiting names - requesting file " + f) : null);
            let url = window.location.origin + "/cloud/" + f;
            jQuery.ajax({
                url: url,
                success: function (data) {
                    ((loglevel >= 1) ? console.log(t() + " load face data - waiting names - received response from server") : null);
                    ((loglevel >= 3) ? console.log(t() + " load face data - waiting names - received from server: data= " + data) : null);
                    readWaitingNames(data);
                    downloadFaceData();
                },
                error: function (data) {
                    ((loglevel >= 1) ? console.log(t() + " empty names file") : null);
                    downloadFaceData();
                },
                async: true
            });
        }
    } else if (filesToLoad.faces.length > 0) {
        // Read faces.json per directory.
        // faces.json contains the information about the postition of faces in 
        // images and their names.
        let f = filesToLoad.faces.shift();
        ((loglevel >= 2) ? console.log(t() + " load face data - requesting file " + f) : null);
        let url = window.location.origin + "/cloud/" + f;
        jQuery.ajax({
            url: url,
            success: function (data) {
                ((loglevel >= 1) ? console.log(t() + " load face data - received response from server") : null);
                ((loglevel >= 3) ? console.log(t() + " load face data - received from server: file=" + f + ", data= " + data) : null);
                readFaces(data, f);
                downloadFaceData();
            },
            error: function (data) {
                ((loglevel >= 1) ? console.log(t() + " empty faces file") : null);
                downloadFaceData();
            },
            async: true
        });
    } else {
        ((loglevel >= 0) ? console.log(t() + " load face data and names - finished - last faces.json file was downloaded") : null);
        if (stopLoadingImages) {
            return;
        }
        filterAndSort();
        appendPictures();
        isWaitingForInitialDownload = false;
    }
}


//------------------------------------------------------------------------------
//--- share begin --------------------------------------------------------------

function postDownloadSharedFaces() {
    if (files_shared.length > 0) {
        let f = files_shared.shift();
        if (f[4]) {
            postDownloadSharedFaces();  // this is the owner himself
        } else {
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
                        postSharedFaces(data["faces"], hash, url);
                    }
                    postDownloadSharedFaces();
                },
                error: function (data, status) {
                    ((loglevel >= 1) ? console.log(t() + " failed to downloaded shared faces from url=" + url + ", got status=" + status) : null);
                    postDownloadSharedFaces();
                }
            });
        }
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
    ((loglevel >= 1) ? console.log(t() + " post cleanup shared - requesting url = " + postURL) : null);

    $.post(postURL, {}, function (data) {
        ((loglevel >= 1) ? console.log(t() + " post " + postURL + " - received server " + data['status'] + ", message: " + data['message']) : null);
    },
            'json');
}


//--- share end --------------------------------------------------------------
//------------------------------------------------------------------------------

/*
 * Read names that where set by the user (browser) and are not processed
 * yet by the face recognition. The server will send old names and the browser will
 * show thoses old names to the user. The user will irritated because he has 
 * set the name already and thinks something went wrong.
 * 
 * This function is useing face id's. This is not safe if more than one
 * detector and one model is generating the faces.
 */
function readWaitingNames(faces_waiting) {
    let i = 0;
    while (faces_waiting.id[i]) {
        let id = faces_waiting.id[i];
        let name = faces_waiting.name[i];
        let url = "/cloud/" + channel_name + "/" + faces_waiting.file[i];
        let pos = faces_waiting.position[i].map(Number);  // convert array of strings to array of numbers
        names_waiting.push({id: id, name: name, url: url, pos: pos});
        ((loglevel >= 2) ? console.log(t() + " stored waiting face id=" + id + ", name=" + name + ", url=" + url) : null);
        i++;
    }
}

function getWaitingNameForFace(face) {
    ((loglevel >= 3) ? console.log(t() + " getWaitingNameForFace(" + face.name + ") - start... face.url =" + face.url) : null);
    let i;
    for (i = 0; i < names_waiting.length; i++) {
        let face_waiting = names_waiting[i];
        if (face_waiting.url === face.url) {
            let face_existing = isSameFace(face, face_waiting);
            if (face_existing) {
                ((loglevel >= 3) ? console.log(t() + " getWaitingNameForFace(" + face.name + ") - yes, found waiting face: name=" + face.name + " of face id= " + face.id + " that was sent to server, url=" + face.url) : null);
                return names_waiting[i].name;
            }
        }
    }
    ((loglevel >= 3) ? console.log(t() + " getWaitingNameForFace(" + face.name + ") - face not found in waiting faces. Returning empty string...") : null);
    return "";
}

function correctToWaitingName(face) {
    ((loglevel >= 3) ? console.log(t() + " correctToWaitingName(" + face.id + ") - try to replace this name by a name that is in state waiting for the face recognition, face id= " + face.id + " (url=" + face.url + ")") : null);
    let n = getWaitingNameForFace(face);
    if (n !== "") {
        face.name = n;
        ((loglevel >= 3) ? console.log(t() + " correctToWaitingName(" + face.id + ") - yes, found a name=" + face.name + " for face id= " + face.id) : null);
        if(face.name.length > 50 && face.name.indexOf(" ") === -1) {
            // name is still an xchan_hash that was not replaced by a real name
            ((loglevel >= 3) ? console.log(t() + " correctToWaitingName(" + face.id + ") - the name=" + face.name + " seems to be an xchan_hash that was not replaced by a real name yet (url=" + face.url + ")") : null);
            face.name = replaceNameForXchan_hash(face.name);
        }
        ((loglevel >= 3) ? console.log(t() + " correctToWaitingName(" + face.id + ") - returning corrected name=" + face.name + " (url=" + face.url + ")") : null);
    }
    return face;
}

function readFaces(imgs, csvFile) {
    let i = 0;
    while (imgs.file[i]) {
        let face = {
            id: imgs.id[i],
            url: "/cloud/" + channel_name + "/" + imgs.file[i],
            pos: imgs.position[i],
            face_nr: imgs.face_nr[i],
            name: imgs.name[i],
            name_preserved: false,
            name_recognized: imgs.name_recognized[i],
            time_named: imgs.time_named[i],
            time: sort_exif ? imgs.exif_date[i] : imgs.mtime[i],
            csv_file: csvFile,
            sent: false
        };
        i++;
        if (me) {
            if (me !== face.name) {
                continue;
            }
        }
        if (hasContactToBeRemoved(face.name)) {
            face.name = "";
            removeContactNotCloseEnough(face);
        } else if (hasContactToBeRemoved(face.name_recognized)) {
            face.name_recognized = "";
        } else {
            face.name = replaceNameForXchan_hash(face.name);
            face.name_recognized = replaceNameForXchan_hash(face.name_recognized);
        }
        appendNameToDrowDown(face.name);
        appendFaceToImages(face);
    }
}

function removeContactNotCloseEnough(face) {
    unsentNames.push({
        "id": face.id,
        "file": face.url,
        "position": face.pos,
        "name": ""
    });
    postNames();
}

function appendFaceToImages(face) {
    var existingFace = getFaceForId(face.id);
    if (existingFace) {
        ((loglevel >= 2) ? console.log(t() + " append face to image in data array - face does exists already - updating face, id=" + face.id + ", url=" + face['url']) : null);
        updateFace(face);
        return;
    }
    var appended = false;
    images.forEach(image => {
        if (image['url'] === face['url']) {
            var existingFace = getFaceAtSamePosition(image, face);
            if (!existingFace) {
                face = correctToWaitingName(face);
                image['faces'].push(face);
                ((loglevel >= 2) ? console.log(t() + " append face to image in data array - new face id=" + face.id + ", url= " + face['url']) : null);
            } else {
                face.id = existingFace.id;
                updateFace(face);
            }
            appended = true;
        }
    });
    if (appended) {
        return;
    }
    if (face.name.trim() === "-ignore-") {
        return;
    }
    var faces = [];
    face = correctToWaitingName(face);
    faces.push(face);
    var image = {id: imageCounter++, url: face['url'], faces: faces, pass: true, time: face['time']};
    images.push(image);
    ((loglevel >= 2) ? console.log(t() + " append face to image in data array - finished - created new image = " + face['url']) : null);
}


function editName(faceFrame) {
    hideEditFrame();

    var id = $(faceFrame).attr("id");
    ((loglevel >= 1) ? console.log(t() + " user starts do edit face id =" + id) : null);

    ((loglevel >= 2) ? console.log(t() + " figure out where to position the input field and buttons") : null);


    var r = getCssValueAsNumber($(faceFrame).css("right"));
    var l = getCssValueAsNumber($(faceFrame).css("left"));
    var top = getCssValueAsNumber($(faceFrame).css("top"));
    var bottom = getCssValueAsNumber($(faceFrame).css("bottom"));

    var par = $(faceFrame).parent();
    var img = par.find('img:first');
    var imgId = img.attr("id");

    var offsetTop = faceFrame.offsetTop;
    var faceFrameId = "face-frame-" + imgId // example: face-frame-img-1 ... is create dynamically while appending pictures
    var faceFrameEdit = document.getElementById(faceFrameId);
    faceFrameEdit.innerHTML = faceEditControls;
    var options = document.getElementById("face-name-list-search").innerHTML;
    document.getElementById("face-name-list-set").innerHTML = options;

    //faceFrameEdit.style.top = offsetTop + "px";
    if (top > bottom) {
        faceFrameEdit.style.bottom = bottom + 5 + "px";
        faceFrameEdit.style.top = "";
    } else {
        faceFrameEdit.style.top = top + 5 + "px";
        faceFrameEdit.style.bottom = "";
    }
    if (l > r) {
        faceFrameEdit.style.right = r + 5 + "px";
        faceFrameEdit.style.left = "";
    } else {
        faceFrameEdit.style.left = l + 5 + "px";
        faceFrameEdit.style.right = "";
    }
    var name_shown = faceFrame.innerText;
    document.getElementById("input-face-name").value = name_shown;
    document.getElementById("input-face-name").setAttribute("face_id", id); // to read out the face id if the user edits the name
    document.getElementById("input-face-name").focus();

    ((loglevel >= 1) ? console.log(t() + " edit frame  was opened and shows name = " + name_shown) : null);
}

function replaceNameWithXchan_hash(name) {
    ((loglevel >= 3) ? console.log(t() + " replaceNameWithXchan_hash(" + name + "): start") : null);
    for (let id in contacts) {
        let contact = contacts[id];
        let contact_name = contact[1] + " (" + contact[2] + ")";
        if (name === contact_name) {
            ((loglevel >= 3) ? console.log(t() + " replaceNameWithXchan_hash(" + name + "): returning id=" + id) : null);
            return id;
        }
    }
    ((loglevel >= 3) ? console.log(t() + " replaceNameWithXchan_hash(" + name + "): no id found for this name") : null);
    return name;
}

function replaceNameForXchan_hash(chan_hash) {
    ((loglevel >= 3) ? console.log(t() + " replaceNameForXchan_hash(" + chan_hash + "): start") : null);
    for (let id in contacts) {
        if (id === chan_hash) {
            let contact = contacts[id];
            let contact_name = contact[1] + " (" + contact[2] + ")";
            ((loglevel >= 3) ? console.log(t() + " replaceNameForXchan_hash(" + chan_hash + "): returning contact_name=" + contact_name) : null);
            return contact_name;
        }
    }
    ((loglevel >= 3) ? console.log(t() + " replaceNameForXchan_hash(" + chan_hash + "): no contact name found for this chan_hash") : null);
    return chan_hash;
}

/*
 * Why this function?
 * The user has tagged a contact.
 * This is permitted because the closeness of the contact is less or equal the
 * closness required by the addon (see pages settings or sharing).
 * 
 * What could happen?
 * The user now changes the closeness of the contact.
 * Assume the value of the closeness became now greater
 * then the required closness by the addon.
 * The user is not longer allowed to tag this contact.
 * The contact should now be removed from the images (name set back).
 */
function hasContactToBeRemoved(name) {
    // Guess if name from contact list. If yes it is a hash.
    if (name.length > 80 && !/\s/.test(name)) {
        // Is most probably xchan_hash. The xchan_hash is 86 chars long at the time of writing.
        let foundName = replaceNameForXchan_hash(name);
        if (foundName === name) {
            // no contact for xchan_hash
            return true;
        }
    }
    return false;
}

function appendContact(contact) {
    let contact_name = contact[1] + " (" + contact[2] + ")";
    ((loglevel >= 3) ? console.log(t() + " appendContact( (" + contact[1] + " (" + contact[2] + ") ) - start") : null);
    appendNameToDrowDown(contact_name);
    ((loglevel >= 3) ? console.log(t() + " appendContact( (" + contact[1] + " (" + contact[2] + ") ) - finished") : null);
}

function appendNameToDrowDown(name) {
    ((loglevel >= 3) ? console.log(t() + " appendNameToDrowDown(" + name + "): start name") : null);
    if (me) {
        ((loglevel >= 3) ? console.log(t() + " appendNameToDrowDown(" + name + "): seem to be myself") : null);
        let temp_name = replaceNameWithXchan_hash(name);
        if (me !== temp_name) {
            ((loglevel >= 3) ? console.log(t() + " appendNameToDrowDown(" + name + "): is not me because me=" + me + " is not equel to temp_name=" + temp_name) : null);
            return;
        }
    }
    if (!name) {
        ((loglevel >= 2) ? console.log(t() + " appendNameToDrowDown(" + name + "): name is null") : null);
        return;
    }
    if (name === "") {
        ((loglevel >= 3) ? console.log(t() + " appendNameToDrowDown(" + name + "):: name is empty") : null);
        return;
    }
    // append in ui
    if (!isNameInList(name, "face-name-list-search")) {
        $(".face-name-list").append("<option value=\"" + name + "\">" + name + "</option>");
        ((loglevel >= 1) ? console.log(t() + " appendNameToDrowDown(" + name + "): appending name = " + name + " to drop down list") : null);
    }
    // append to list received names
    var i;
    for (i = 0; i < receivedNames.length; i++) {
        let existing_name = receivedNames[i].name;
        if (existing_name === name) {
            ((loglevel >= 2) ? console.log(t() + " appendNameToDrowDown(" + name + "): name does exist already in array or received names (drop down list)") : null);
            return;
        }
    }
    ((loglevel >= 3) ? console.log(t() + " appendNameToDrowDown(" + name + "): appending name to array of received names too") : null);
    receivedNames.push(name);
}

function isNameInList(name, selector) {
    ((loglevel >= 2) ? console.log(t() + " is name = " + name + " in list with selector = " + selector + " ?") : null);
    var o = document.getElementById(selector);
    var i;
    for (i = 0; i < o.options.length; i++) {
        var text = o.options[i].text;
        if (text === name) {
            ((loglevel >= 2) ? console.log(t() + " yes") : null);
            return true;
        }
    }
    ((loglevel >= 2) ? console.log(t() + " no") : null);
    return false;
}


function setName() {
    var face_id_full = document.getElementById("input-face-name").getAttribute("face_id");
    var name_shown = document.getElementById(face_id_full).innerText;
    var name = document.getElementById("input-face-name").value.trim();
    ((loglevel >= 1) ? console.log(t() + " start to set new face name new = " + name + " was name = " + name_shown + " with face id=" + face_id_full) : null);
    if (name === name_shown) {
        ((loglevel >= 1) ? console.log(t() + " name did not change") : null);
        var isVerified = document.getElementById(face_id_full).getAttribute("isVerified");
        if (isVerified === "1") {
            ((loglevel >= 1) ? console.log(t() + " Do nothing, was verified already") : null);
            hideEditFrame();
            return;
        }
    }
    if (name !== "") {
        if (!isNameInList(name, "face-name-list-search")) {
            ((loglevel >= 1) ? console.log(t() + " The new name = " + name + " will be appened to the name list (as option value) as soon as it was successfully sent to the server.") : null);
            appendNameToDrowDown(name);
        }
        ((loglevel >= 1) ? console.log(t() + " The style of the frame will be changed after it was successfully sent to the server.") : null);//    document.getElementById(face_id_full).style.border = "medium solid green";
    }
    preparePostName(face_id_full, name);
}

function setNameUnkown() {
    var face_id_full = document.getElementById("input-face-name").getAttribute("face_id");
    ((loglevel >= 1) ? console.log(t() + " set face name > unknown - face id=" + face_id_full) : null);
    ((loglevel >= 1) ? console.log(t() + " The style of the frame will be changed after it was successfully sent to the server.") : null);
    preparePostName(face_id_full, -1);
}

function setNameIgnore() {
    var face_id_full = document.getElementById("input-face-name").getAttribute("face_id");
    ((loglevel >= 1) ? console.log(t() + "set face name > ignore - face id=" + face_id_full) : null);
    ((loglevel >= 1) ? console.log(t() + " The style of the frame will be changed after it was successfully sent to the server.") : null);
    preparePostName(face_id_full, -2);
}

function preparePostName(face_id_full, name, isMe) {
    ((loglevel >= 1) ? console.log(t() + " start to prepare name  to send it to the server, face id = " + face_id_full) : null);
    var face_id = face_id_full.split("-")[1];
    // var name_old = document.getElementById(face_id_full).innerText;
    var face = getFaceForId(face_id);
    if (name === -2) {
        // clicked "ignore"
        name = "-ignore-";
    } else if (name === -1) {
        // clicked "unknown"
        name = "";
        face.time_named = "dummy time stamp for styling frame";
    } else if (name === -3) {
        // let the face recognition try to guess
        name = "";
    }
    setPreservedNameFaceForId(face_id, name);  // to preserve the name when the face recognition is still running
    face.name = name;
    var file = face['url'];
    var position = face['pos'];
    name = name.replace(",", " ");  // format of csv
    name = replaceNameWithXchan_hash(name);
    if (isMe) {
        unsentNamesMe.push({
            "id": face_id,
            "file": file,
            "position": position,
            "name": name
        });
        return;
    } else {
        unsentNames.push({
            "id": face_id,
            "file": file,
            "position": position,
            "name": name
        });
    }
    hideEditFrame();
    styleFaceFrame(face);
    postNames();
}


function removeNameFromUnsentList(face) {
    if (!face) {
        ((loglevel >= 1) ? console.log(t() + " remove face from unsent list - can not remove face id from unsent list because the id is null (received as server response)") : null);
        return false;
    }
    ((loglevel >= 2) ? console.log(t() + " remove face from unsent list - looking for face = " + JSON.stringify(face)) : null);
    var faceID = face.id;
    var hasRemoved = false;
    var i;
    for (i = 0; i < unsentNames.length; i++) {
        var id = unsentNames[i].id;
        if (faceID === id) {
            unsentNames.splice(i, 1);
            ((loglevel >= 2) ? console.log(t() + " remove face from unsent list - removed face id = " + id + " from list of unsent faces") : null);
            hasRemoved = true;
            break;
        }
    }
    ((loglevel >= 1) ? console.log(t() + " remove face from unsent list - Success yes/no for: face was removed from list of unsent faces : " + hasRemoved) : null);
    return hasRemoved;
}



function postNames() {
    ((loglevel >= 1) ? console.log(t() + " post names - post the next name in the list of unsent faces ") : null);
    if (unsentNames.length === 0) {
        ((loglevel >= 1) ? console.log(t() + " post names - no name left to send ") : null);
        clearCounterNamesSending();
        if (!isFaceRecognitionRunning && immediateSearch) {
            postRecognize();
        } else if (me) {
            postRecognize();
        }
        return;
    }
    var action = new Array();
    action["face"] = unsentNames[0];
    var nameString = JSON.stringify(unsentNames[0]);
    ((loglevel >= 1) ? console.log(t() + " post names - about to send the first name in the list of unsent name to the server. Post value is : " + nameString) : null);
    animate_on();
    setCounterNamesSending();
    let postURL = url_addon + "/name";
    if (me) {
        postURL += "/me"
    }
    ((loglevel >= 1) ? console.log(t() + " url =  " + postURL) : null);
    $.post(postURL, {face: unsentNames[0]}, function (data) {
        ((loglevel >= 1) ? console.log(t() + " post names - received response from server after posting a name") : null);
        if (data['status']) {
            ((loglevel >= 1) ? console.log(t() + " post names - receiced server response to sending the name was without errors") : null);
            if (removeNameFromUnsentList(data['face'])) {
                postNames();
            } else {
                ((loglevel >= 1) ? console.log(t() + " post names - stop sendings names because failed to removed a name/face frome the unsent list. The name/face was received from the server (as response to set its name)") : null);
                clearCounterNamesSending();
            }
        } else {
            ((loglevel >= 0) ? console.log(t() + " post names - error sending name. Server responded with: " + data['message']) : null);
            clearCounterNamesSending();
        }
    },
            'json');
}

$(".faces-search-inputs").focus(function () {
    stopLoadingImages = true;
});


function addSearchName() {
    ((loglevel >= 1) ? console.log(t() + " selected a search name") : null);
    var name = document.getElementById("input-search-name").value.trim();
    if (name == "") {
        ((loglevel >= 1) ? console.log(t() + " name was null") : null);
        return;
    }
    if (!isNameInList(name, "face-name-list-search")) {
        ((loglevel >= 1) ? console.log(t() + " you can not search for a name that is not known") : null);
        return;
    }
    ((loglevel >= 1) ? console.log(t() + " search name = " + name) : null);
    var buttons = document.querySelectorAll('[searchnameid]');
    document.getElementsByClassName('btn-face-search-name');
    var i;
    for (i = 0; i < buttons.length; i++) {
        var text = names[i].innerText.trim();
        if (text == name) {
            ((loglevel >= 1) ? console.log(t() + " seach name already selected " + name) : null);
            return;
        }
    }
    $("#face-active-filter-names").append("<button class=\"btn btn-face-search-name\">" + name + " <i class=\"fa fa-remove fa-lg faces-search-inputs\"></i></button>");
    ((loglevel >= 1) ? console.log(t() + " created button for search name =  " + name) : null);
    search();
}

$('#face-active-filter-names').on('click', '.btn-face-search-name', function () {
    ((loglevel >= 1) ? console.log(t() + " remove button for search name =  " + name) : null);
    $(this).remove();
    search();
});

$(".face-date").change(function () {
    ((loglevel >= 1) ? console.log(t() + " user changed search date  ") : null);
    let from = $("#face-date-from").val();
    if (from.startsWith("0")) {
        return;
    }
    let to = $("#face-date-to").val();
    if (to.startsWith("0")) {
        return;
    }
    search();
});

$("#face-search-and").change(function () {
    var buttons = document.getElementsByClassName("btn-face-search-name");
    if (buttons.length < 1) {
        return;
    }
    ((loglevel >= 1) ? console.log(t() + " user changed search AND/OR  ") : null);
    search();
});


var filterStringServer = "";
var oldestImageLoadedId = "";
var mostRecentImageLoadedId = "";
var filter = null;
function search() {
    ((loglevel >= 1) ? console.log(t() + " search was called ") : null);
    $("#face-panel-pictures").empty();
    oldestImageLoadedId = "";
    mostRecentImageLoadedId = "";
    filterAndSort();
    picturesProcessedID = [];
    countDownloadedImages = 0;
    // stop loading images
    ((loglevel >= 1) ? console.log(t() + " Remove pictures ") : null);
    $("#face-panel-pictures").empty();
    animate_on();
    appendPictures();
}


var countFilteredImages = 0;

function filterAndSort() {
    createFilterString();
    filterImages();
    images.sort(compareExifDates);
    stopLoadingImages = false;
}

function filterImages() {
    if (!filter) {
        countFilteredImages = images.length;
        return;
    }
    countFilteredImages = 0;
    var names = filter.names;
    var i;
    for (i = 0; i < images.length; i++) {
        var faces = images[i].faces;

        var passedTime = false;
        var passedName = false;

        var date = images[i].time;
        if (!date || date === "") {
            ((loglevel >= 3) ? console.log(t() + " image id=" + images[i].id + " did not pass the filter, no date found in image") : null);
            images[i]['pass'] = false;
            continue;
        }
        var splittees = date.split("T");
        if (splittees.length !== 2) {
            ((loglevel >= 3) ? console.log(t() + " image id=" + images[i].id + " did not pass the filter, no wrong date format found in image, missing T") : null);
            images[i]['pass'] = false;
            continue;
        }
        date = splittees[0];
        if (date >= filter.from && date <= filter.to) {
            passedTime = true;
            if (names.length === 0) {
                passedName = true;
            } else {
                var and = filter.and;
                for (k = 0; k < names.length; k++) {
                    var name = names[k];
                    var passedName = false;
                    for (j = 0; j < faces.length; j++) {
                        var face = faces[j];
                        if ((face.name !== "" && name === face.name) || (face.name === "" && face.time_named === "" && name === face.name_recognized)) {
                            passedName = true;
                            break;
                        }
                    }
                    if (!and && passedName) {
                        break;
                    }
                    if (and && !passedName) {
                        break;
                    }
                }
            }
        }

        if (passedName && passedTime) {
            images[i]['pass'] = true;
            countFilteredImages += 1;
            ((loglevel >= 3) ? console.log(t() + " image id=" + images[i].id + " passed the filter") : null);
        } else {
            images[i]['pass'] = false;
            ((loglevel >= 3) ? console.log(t() + " image id=" + images[i].id + " did not pass the filter") : null);
        }
    }
}

function compareExifDates(a, b) {
    if (a.time < b.time) {
        if (sortDirectionReverse) {
            return -1;
        } else {
            return 1;
        }
    }
    if (a.time > b.time) {
        if (sortDirectionReverse) {
            return 1;
        } else {
            return -1;
        }
    }
    return 0;
}


function createFilterString() {
    $("#input-search-name").val("");

    var filterNames = [];
    ((loglevel >= 1) ? console.log(t() + " look for search buttons containing the search names ") : null);
    var buttons = document.getElementsByClassName("btn-face-search-name");
    var i;
    for (i = 0; i < buttons.length; i++) {
        var name = buttons[i].innerText.trim();
        filterNames.push(name);
        ((loglevel >= 1) ? console.log(t() + " append search name=" + name) : null);
    }

    toogleAndCheckbox();
    var checked = $("#face-search-and")[0].checked;
    ((loglevel >= 1) ? console.log(t() + " AND = " + checked) : null);

    var from = $("#face-date-from").val();
    var to = $("#face-date-to").val();
    ((loglevel >= 1) ? console.log(t() + " from = " + from) : null);
    ((loglevel >= 1) ? console.log(t() + " to = " + to) : null);

    filter = {"from": from, "to": to, "names": filterNames, "and": checked};
}

function toogleAndCheckbox() {
    var buttons = document.getElementsByClassName("btn-face-search-name");
    if (buttons.length > 1) {
        $(".face-name-and-buttons").css("visibility", "visible");
    } else {
        $(".face-name-and-buttons").css("visibility", "hidden");
    }
}



function getCssValueAsNumber(s) {
    ((loglevel >= 3) ? console.log(t() + " get CSS value = " + s + " as number") : null);
    var i = 0;
    if (isNaN(s)) {
        if (s.indexOf(".") >= 0) {
            s = s.substring(0, s.indexOf("."));
        }
        if (s.indexOf("px") >= 0) {
            s = s.substring(0, s.indexOf("px"));
        }
        i = parseInt(s);
        ((loglevel >= 2) ? console.log(t() + " return CSS value = " + s + " as number = " + i) : null);
        return i;
    } else {
        ((loglevel >= 1) ? console.log(t() + " return CSS value = " + s + " as  = " + s + " (failed to parse)") : null);
        return s;
    }
}


function hideEditFrame() {
    $(".face-frame-edit").css({top: '99%'});
    $(".face-frame-edit").html("");
    ((loglevel >= 1) ? console.log(t() + " hide edit frame") : null);
    showFooterButtons();
}

$("#button-faces-filter").click(function () {
    ((loglevel >= 1) ? console.log(t() + " user clicked on filter button") : null);
    showFooterFilter();
});

function showFooterButtons() {
    $("#face-footer-buttons").css("height", "40px");
    $("#face-footer").css("height", "0px");
    ((loglevel >= 1) ? console.log(t() + " show footer buttons") : null);
}

function showFooterFilter() {
    $("#face-footer-buttons").css("height", "0px");
    $("#face-footer").css("height", "170");
    toogleAndCheckbox();
    ((loglevel >= 1) ? console.log(t() + " show footer filter") : null);
    document.getElementById("input-search-name").focus();
}


var isShowFrameON = true;
$("#button-faces-hide-frames").click(function () {
    ((loglevel >= 1) ? console.log(t() + " user clicked button to show/hide faces") : null);
    if (isShowFrameON) {
        $('#button-faces-hide-frames').find('.fa').removeClass("fa-eye-slash");
        $('#button-faces-hide-frames').find('.fa').addClass("fa-eye");
        var frames = document.getElementsByClassName("face-frame-name");
        var k;
        for (k = 0; k < frames.length; k++) {
            frames[k].style.border = "rgba(255,255,255,.5)";
            frames[k].getElementsByClassName("face-name-shown")[0].innerHTML = "";
        }
        isShowFrameON = false;
    } else {
        $('#button-faces-hide-frames').find('.fa').removeClass("fa-eye");
        $('#button-faces-hide-frames').find('.fa').addClass("fa-eye-slash");
        styleAllAgain();
        isShowFrameON = true;
    }
});

$("#button_faces_zoom_in").click(function () {
    ((loglevel >= 1) ? console.log(t() + " user clicked zoom in") : null);
    if (zoom > 0) {
        zoom -= 1;
    } else {
        return;
    }
    if (zoom < minZoomLoaded) {
        //search();
        minZoomLoaded = zoom;
    } else {
        zoomPictures();
    }
});

$("#button_faces_zoom_out").click(function () {
    ((loglevel >= 1) ? console.log(t() + " user clicked zoom out") : null);
    if (zoom < 6) {
        zoom += 1;
    } else {
        return;
    }
    zoomPictures();
});

function zoomPictures() {
    if (!isImageDownloadFinished()) {
        ((loglevel >= 1) ? console.log(t() + " zoom ignored because download of images not finished yet") : null);
    }
    loadedCountMax = zoom * loadedCountMaxMultiplier;
    if (zoom < 1) {
        var containers = document.getElementsByClassName("img-container");
        var i;
        for (i = 0; i < containers.length; i++) {
            var img_container = containers[i];
            var face_conatiner = img_container.getElementsByClassName("face-container")[0];
            var img = face_conatiner.getElementsByTagName("img")[0];
            px_width = img.naturalWidth;
            img_container.style.width = px_width + "px";
            img.style.width = px_width + "px";
        }
    } else {
        $(".img-face").css("width", "100%");
        $(".img-container").css("width", "100%");
        $(".face-img-spacer").remove();
        ((loglevel >= 1) ? console.log(t() + " zoom = " + (100 / zoom) + "%") : null);
        var containers = document.getElementsByClassName("img-container");
        for (i = 0; i < containers.length; i++) {
            containers[i].classList.add("img-container-zoomable");
        }
        zoomLastPictures();
        hideEditFrame();
    }
    
    postConfigZoom(zoom);
}

function zoomLastPictures(img) {
    if (img) {
        if (zoom < 1) {
            var img_container = img.parentElement.parentElement;
            px_width = img.naturalWidth;
            img_container.style.width = px_width + "px";
            img.style.width = px_width + "px";
            return;
        }
        $(".img-container-zoomable").css("width", "100%");  // to prevent that the images get smaller from image to imgage for a zoom level greater than 2
    }
    if (zoom === 1) {
        return;
    }
    //var w = $("#face-panel-pictures").width();
    let w = document.getElementById("face-panel-pictures").offsetWidth;
    var containers = document.getElementsByClassName("img-container-zoomable");
    if (containers.length === 1) {
        return;
    }
    // if (containers.length < zoom) {
    //     $(".img-container-zoomable").css("width", (100 / zoom).toString() + "%");
    //     return;
    // }
    var zoomendContainers = [];
    var zoomenedImages = [];
    var h = 0;
    var w_all = 0;
    var i;
    for (i = 0; i < containers.length; i++) {
        var img_container = containers[i];
        var img = img_container.getElementsByTagName("img")[0];
        if (h === 0) {
            h = img.naturalHeight;
            w_all = w_all + img.naturalWidth;
            zoomenedImages[i] = ({h: img.naturalHeight, w: img.naturalWidth, factor: 1});
        } else {
            var factor = h / img.naturalHeight;
            w_all = w_all + factor * img.naturalWidth;
            var factor_all = w / w_all;
            zoomenedImages[i] = ({w: img.naturalWidth, factor: factor});
            for (j = 0; j <= i; j++) {
                var w_pix = zoomenedImages[j]["w"] * zoomenedImages[j]["factor"] * factor_all;
                var w_percent = w_pix * 100 / w;
                containers[j].style.width = w_percent + "%";
                zoomendContainers[j] = containers[j];
            }
        }
        if (i === zoom - 1) {
            var k;
            for (k = 0; k < zoomendContainers.length; k++) {
                zoomendContainers[k].classList.remove("img-container-zoomable");
            }
            var id = img_container.id.split("-")[2];
            id = img_container.id;
            //$("<div class=\"img-container face-img-spacer\"  style=\"min-width: 100%; min-height: 1px;\"> </div>").insertAfter("#" + id);
            $("<div class=\"img-container face-img-spacer\"  style=\"min-width: 100%; height: 1px;\"> </div>").insertAfter("#" + id);
            zoomLastPictures();
            return;
        }
    }
}

function removeImagesNotLoaded() {
    let img_count = 0;
    let containers = document.getElementsByClassName("img-container");
    let i = containers.length - 1;
    for (i; i >= 0; i--) {
        let img_container = containers[i];
        let face_container = img_container.getElementsByClassName("face-container")[0];
        if (!face_container) {
            continue;
        }
        img_count += countRemoveMeImages(face_container);
        let img = face_container.getElementsByTagName("img")[0];
        px_width = img.naturalWidth;
        if (px_width === 0) {
            containers[i].remove();
        }
    }
    if (img_count > 0) {
        document.getElementById("face-panel-remove-button").style.display = "block";
        document.getElementById("face-span-image-count").textContent = img_count;
    }
}

function countRemoveMeImages(face_container) {
    if (!me) {
        return 0;
    }
    let face_frames = face_container.getElementsByClassName("face-name-shown");
    let k = 0;
    for (k; k < face_frames.length; k++) {
        let face_id = face_frames[k].textContent;
        let face = getFaceForId(face_id);
        let face_name = replaceNameWithXchan_hash(face.name);
        if (face_name === me) {
            preparePostName("dummy-" + face_id, -2, true);
            return 1;
        }
    }
    return 0;
}

function removeMe() {
    document.getElementById("face-panel-remove-button").style.display = "none";
    unsentNames.push.apply(unsentNames, unsentNamesMe);
    unsentNamesMe = [];
    postNames();
}

function openSingleImage(img) {
    var url = img.getAttribute('src');
    var url_large = url.replace(/-\d$/, "");
    window.open(url_large, "_blank");
}

function appendPicture(img) {
    ((loglevel >= 1) ? console.log(t() + " append picture: start to show image to user, image = " + JSON.stringify(Object.assign({}, img))) : null);
    var html = "";
    var faces = img.faces;
    var i;
    for (i = 0; i < faces.length; i++) {
        // row from db table face_encodings (joined with attach.hash to complete the URL)
        var face = faces[i];
        if (i === 0) {
            var url = face.url;
            var width = (100 / zoom).toString() + "%";
            //html += "<div class=\"img-container img-container-zoomable\" id=\"img-container-" + img.id + "\" width=\"" + width + "\">";
            html += "<div class=\"img-container img-container-zoomable\" id=\"img-container-" + img.id + "\">";
            html += "   <div class=\"face-container\">";
            html += "       <img src=\"" + url + "\" id=\"img-" + img.id + "\" class=\"img-face\" onload=\"setFrameSizes(this)\" onerror=\"removeImage(this)\" onclick=\"hideEditFrame(false)\" ondblclick=\"openSingleImage(this)\">";
        }
        ((loglevel >= 2) ? console.log(t() + " append picture: face number = " + i + " with face id=" + face.id + " to image with id=" + img.id) : null);
        var top = 1;
        var left = 1;
        var bottom = 98;
        var right = 98;
        html += "           <div class=\"face-frame face-frame-name\" id=\"face-" + face.id + "\" model=\"" + face.model + "\" style=\"top: " + top + "%;left: " + left + "%; bottom: " + bottom + "%; right: " + right + "%;\" onclick=\"editName(this)\">";
        html += "               <h4 class=\"face-name-shown\">" + face.id + "</h4>";
        html += "           </div>";
        if (i === faces.length - 1) {
            html += "       <div class=\"face-frame face-frame-edit\" id=\"face-frame-img-" + img.id + "\">"; // picture id from table attach (joined with table face_encodings)
            html += "       </div>";
            html += "   </div>";
            html += "</div>";
        }
    }
    if (mostRecentImageLoadedId == "") {
        mostRecentImageLoadedId = img.id;
        ((loglevel >= 2) ? console.log(t() + " append picture:  most recent image id: " + mostRecentImageLoadedId) : null);
    }

    if (oldestImageLoadedId == 0) {
        oldestImageLoadedId = img.id;
        ((loglevel >= 2) ? console.log(t() + " append picture:  oldest image id: " + oldestImageLoadedId) : null);
    } else if (img.id < oldestImageLoadedId) {
        oldestImageLoadedId = img.id;
        ((loglevel >= 2) ? console.log(t() + " append picture:  oldest image id: " + oldestImageLoadedId) : null);
    }
    if (stopLoadingImages) {
        return;
    }
    animate_on();
    $("#face-panel-pictures").append(html);
}

function getImageForId(id) {
    var i;
    for (i = 0; i < images.length; i++) {
        if (id == images[i].id) {
            ((loglevel >= 3) ? console.log(t() + " getImageForId(" + id + ") - returning image...") : null);
            return images[i];
        }
    }
    ((loglevel >= 3) ? console.log(t() + " getImageForId(" + id + ") - no image found in the list of received images for this id") : null);
    return false;
}

function getFaceForId(id) {
    ((loglevel >= 3) ? console.log(t() + " getFaceForId(" + id + ") - start") : null);
    var i;
    for (i = 0; i < images.length; i++) {
        var faces = images[i].faces;
        for (j = 0; j < faces.length; j++) {
            var face = faces[j];
            if (id == face.id) {
                ((loglevel >= 3) ? console.log(t() + " getFaceForId(" + id + ") - found in the list of received faces. Returning face...") : null);
                return face;
            }
        }
    }
    ((loglevel >= 3) ? console.log(t() + " getFaceForId(" + id + ") - no face found in the list of received faces for this id . Returning false...") : null);
    return false;
}

function setPreservedNameFaceForId(id, name) {
    var i;
    for (i = 0; i < images.length; i++) {
        var faces = images[i].faces;
        for (j = 0; j < faces.length; j++) {
            var face = faces[j];
            if (id === face.id) {
                ((loglevel >= 3) ? console.log(t() + " set preserved name=" + name + "for face id = " + id) : null);
                images[i].faces[j].name_preserved = name;
                return;
            }
        }
    }
}

function updateFace(face) {
    if (me) {
        if (me !== face.name) {
            return;
        }
    }
    face.name = replaceNameForXchan_hash(face.name);
    face.name_recognized = replaceNameForXchan_hash(face.name_recognized);
    var i;
    for (i = 0; i < images.length; i++) {
        var faces = images[i].faces;
        for (j = 0; j < faces.length; j++) {
            var f = faces[j];
            if (f.url === face.url) {
                if (isSameFace(f, face)) {
                    ((loglevel >= 3) ? console.log(t() + " update face in data array - id = " + face.id + ", url=" + face.url) : null);
                    let nameRecognizedOld = f.name_recognized;
                    name_preserved = images[i].faces[j].name_preserved;
                    face = correctToWaitingName(face);
                    if (name_preserved !== false) {
                        // temporarily stored by browser after name was set by the user
                        face.name_preserved = name_preserved;
                        face.name = name_preserved;
                        if (images[i].faces[j].time_named === "") {
                            face.time_named = "dummy time named for frame style";
                        }
                    }
                    images[i].faces[j] = face;
                    if (face.name === "" && face.time_named === "" && nameRecognizedOld !== face.name_recognized) {
                        styleFaceFrame(face);
                    }
                    return true;
                }
            }
        }
    }
    ((loglevel >= 1) ? console.log(t() + " update face in data array - found no faces to update for url = " + face.url + ", id=" + face.id) : null);
    return false;
}

// If faces are updated they have not always the same face id because the are found by different detectors/models.
// Check the position of existing faces (frames arround faces) to avoid multiple frames around a same face.
function getFaceAtSamePosition(image, face) {
    ((loglevel >= 3) ? console.log(t() + " get face at same position - start - position=" + face.pos + ", url=" + face.url + ", face id=" + face.id) : null);
    var faces = image.faces;
    for (j = 0; j < faces.length; j++) {
        var f = faces[j];
        let face_existing = isSameFace(f, face);
        if (face_existing) {
            return face_existing;
        }
    }
    ((loglevel >= 3) ? console.log(t() + " get face at same position - finished - no face found - position=" + face.pos + ", url=" + face.url + ", face id=" + face.id) : null);
    return false;
}

function isSameFace(f, face) {
    // margins left, right, top, bottom in percent
    middle_of_face_x = face.pos[0] + (100 - (face.pos[1] + face.pos[0])) / 2;
    middle_of_face_y = face.pos[2] + (100 - (face.pos[2] + face.pos[3])) / 2;
    end_of_row_face_x = 100 - f.pos[1];
    end_of_row_face_y = 100 - f.pos[3];
    // is middle of face inside row position?
    if ((f.pos[0] < middle_of_face_x) && (middle_of_face_x < (end_of_row_face_x))) {
        if ((f.pos[2] < middle_of_face_y) && (middle_of_face_y < (end_of_row_face_y))) {
            middle_of_row_face_x = f.pos[0] + (100 - (f.pos[1] + f.pos[0])) / 2
            middle_of_row_face_y = f.pos[2] + (100 - (f.pos[2] + f.pos[3])) / 2
            end_of_face_x = 100 - face.pos[1];
            end_of_face_y = 100 - face.pos[3];
            // is middle of row position inside face ?
            if ((face.pos [0] < middle_of_row_face_x) && (middle_of_row_face_x < (end_of_face_x))) {
                if ((face.pos[2] < middle_of_row_face_y) && (middle_of_row_face_y < (end_of_face_y))) {
                    ((loglevel >= 3) ? console.log(t() + " yes, found a face id=" + f.id + " at position=" + f.pos + " in image=" + face.url) : null);
                    return f;
                }
            }
        }
    }
    return false;
}

function setFrameSizes(img) {
    ((loglevel >= 2) ? console.log(t() + " setFrameSizes(" + img.src + ") - start") : null);
    var px_width = img.naturalWidth;
    var px_height = img.naturalHeight;
    ((loglevel >= 2) ? console.log(t() + " img h=" + px_height + "px, w=" + px_width + "px, id=" + img.id) : null);
    var splittees = img.id.split("-");
    var img_id = splittees[1];
    var image = getImageForId(img_id);
    var faces = image.faces;
    var i;
    for (i = 0; i < faces.length; i++) {
        var face = faces[i];
        // What to use depends on the face recognition scripts
        // a) in percent margins [left, right, top, bottom] default
        // b) in pixel:          [x, y, h, w]               from left, top corner in pixel
        var faceLocation = face.pos;
        var nameFrame = document.getElementById("face-" + face.id);
        if (!nameFrame) {
            return;
        }
        ((loglevel >= 3) ? console.log(t() + " setFrameSizes(" + img.src + ") - face id = " + face.id) : null);
        nameFrame.style.top = faceLocation[2] + "%";
        nameFrame.style.left = faceLocation[0] + "%";
        nameFrame.style.bottom = faceLocation[3] + "%";
        nameFrame.style.right = faceLocation[1] + "%";
        styleFaceFrame(face);
    }
    ((loglevel >= 2) ? console.log(t() + " setFrameSizes(" + img.src + ") - finished") : null);
    triggerZoomAfterImageDownload();
}

function removeImage(image) {
    ((loglevel >= 1) ? console.log(t() + " failed to load image (probably no permission), removing... " + image.currentSrc) : null);
    triggerZoomAfterImageDownload();
}


function styleFaceFrame(face) {
    if (!face) {
        ((loglevel >= 2) ? console.log(t() + " style face: for face = null > can not style (update frame style and name) ") : null);
        return;
    }
    ((loglevel >= 2) ? console.log(t() + " style face: for face = " + JSON.stringify(Object.assign({}, face))) : null);
    var nameFrame = document.getElementById("face-" + face.id);
    if (!nameFrame) {
        // no image yet to update (happens after http get = loading the page)
        // ((loglevel >= 1) ? console.log(t() + " no image yet to update (happens 1. after http get = loading the page, or 2. after show face frames again after hidden if images where not displayed yet") : null);
        return;
    }
    var name = "";
    var name_id = "0";
    var isVerified = "0";
    if (!face.name_recognized) {  // This can happen if the user sets the name to "unknown"
        face.name_recognized = "";  // avoid "undefined"
    }


    if (face.name !== "" && face.name === "-ignore-") {
        // The user marked this face as "is not a face"
        document.getElementById("face-" + face.id).remove();
        ((loglevel >= 3) ? console.log(t() + " style face:  ignored face, id = " + face.id + ", url=" + face.url) : null);
        return;
    } else if (face.name === "" && face.time_named !== "") {
        // the user marked this face as "unknow"
        name = "";
        nameFrame.style.border = "medium dotted grey";
        ((loglevel >= 3) ? console.log(t() + " style face: face marked as unknown, id = " + face.id + ", url=" + face.url) : null);
    } else if (face.name !== "") {
        nameFrame.style.border = "rgba(255,255,255,.5)";
        name = face.name;
        isVerified = "1";
        ((loglevel >= 3) ? console.log(t() + " style face:  named face, id = " + face.id + ", url=" + face.url) : null);
    } else if (face.name_recognized !== "") {
        name = face.name_recognized;
        nameFrame.style.border = "medium dashed red";
        ((loglevel >= 3) ? console.log(t() + " style face: recognized face, id = " + face.id + ", url=" + face.url) : null);
    } else {
        nameFrame.style.border = "medium dotted red";
        ((loglevel >= 3) ? console.log(t() + " style face:  no name yet face, id = " + face.id + ", url=" + face.url) : null);
    }
    nameFrame.getElementsByClassName("face-name-shown")[0].innerHTML = name;
    nameFrame.setAttribute("isVerified", isVerified);
    ((loglevel >= 2) ? console.log(t() + " style face:  end - frame shows name = " + name + ", id = " + face.id + ", url=" + face.url) : null);
}

function styleAllAgain() {
    ((loglevel >= 1) ? console.log(t() + " style all again - start") : null);
    var i;
    for (i = 0; i < images.length; i++) {
        var faces = images[i].faces;
        for (j = 0; j < faces.length; j++) {
            var face = faces[j];
            ((loglevel >= 3) ? console.log(t() + " style all again - styleface, id = " + face.id + ", url=" + face.url) : null);
            styleFaceFrame(face);
        }
    }
    ((loglevel >= 1) ? console.log(t() + " style all again - finished") : null);
}

function checkServerStatus(status) {
    if (!status) {
        showServerStatus(0);
    } else if (!isFaceRecognitionRunning) {
        showServerStatus(0);
    } else {
        var proc = status["procid"];
        var utc = status["utc"];
        if (proc != server_procid) {
            server_procid = proc;
            server_time = utc;
            ((loglevel >= 1) ? console.log(t() + " server status with new procid=" + server_procid + ", time=" + server_time) : null);
            showServerStatus(0);
        } else {
            var seconds = (new Date()).getTime() / 1000;
            seconds = Math.round(seconds);
            date = new Date(server_time);
            seconds_stored = date.getTime() / 1000;
            var elapsed = seconds - seconds_stored;
            ((loglevel >= 1) ? console.log(t() + " server status updated with procid=" + server_procid + ", time=" + utc + ", calculated and shown elapsed=" + elapsed + "s") : null);
            showServerStatus(elapsed, status["elapsed"]);

        }
    }
}

function showServerStatus(seconds, elapsed_server) {
    if (seconds !== 0) {
        document.getElementById("faces_server_status").style.visibility = "visible";
        if (elapsed_server > 60) {
            document.getElementById("faces_server_status").style.color = "red";
        } else {
            document.getElementById("faces_server_status").style.color = "green";
        }
        document.getElementById("faces_server_status").innerHTML = seconds + "s";
    } else {
        document.getElementById("faces_server_status").style.visibility = "hidden";
        document.getElementById("faces_server_status").innerHTML = "";
    }
}

async function waitForFinishedFaceDetection(timeToSleep) {
    let waitTimeForResult = timeToSleep;
    isFaceRecognitionRunning = true;
    var url = url_addon + "/status";
    while (isFaceRecognitionRunning) {
        ((loglevel >= 1) ? console.log(t() + " wait for the face detection to finish, url= " + url + ", wait time=" + waitTimeForResult + " ms") : null);
        await sleep(waitTimeForResult);
        waitTimeForResult = 10000; // 10 seconds
        if (isWaitingForInitialDownload) {
            ((loglevel >= 1) ? console.log(t() + " still waiting for initial download of faces and names to end") : null);
        } else {
            var action = new Array();
            var postURL = url_addon + "/status";
            ((loglevel >= 1) ? console.log(t() + " about to get the status of the face detection and recognition: url = " + postURL) : null);
            $.post(postURL, {action}, function (data) {
                ((loglevel >= 1) ? console.log(t() + " received server status " + JSON.stringify(data)) : null);
                if (data['running']) {
                    checkServerStatus(data['status']);
                } else {
                    isFaceRecognitionRunning = false;
                    checkServerStatus(data['status']);
                    if (unsentNames.length === 0) {
                        postDownloadResults();
                        return;
                    } else {
                        //postNames();
                        return;
                    }
                }
            },
                    'json');
        }
    }
}

function sleep(ms) {
    ((loglevel >= 1) ? console.log(t() + " start to sleep for ms = " + ms) : null);
    return new Promise(resolve => setTimeout(resolve, ms));
}

function appendNextPictures() {
    ((loglevel >= 1) ? console.log(t() + " append next picture...") : null);
    if (stopLoadingImages) {
        ((loglevel >= 1) ? console.log(t() + " appending pictures was blocked ") : null);
        return;
    }
    appendPictures();
}

function appendPictures() {
    ((loglevel >= 1) ? console.log(t() + " start to append next picture... pictures processed so far = " + picturesProcessedID.length) : null);
    var k;
    for (k = 0; k < images.length; k++) {
        if (stopLoadingImages) {
            return;
        }
        var img = images[k];
        var id = img.id;
        if (picturesProcessedID.includes(id)) {
            ((loglevel >= 3) ? console.log(t() + " image with id = " + id + " was processed already. Continue...") : null);
            continue;
        }
        if (!img.pass) {
            ((loglevel >= 3) ? console.log(t() + " image with id = " + id + " did not pass the filter. Continue...") : null);
            continue;
        }
        if (loadedCount >= loadedCountMax) {
            ((loglevel >= 1) ? console.log(t() + " loaded " + loadedCount + " images. Scroll down to load more images") : null);
            clearCounterImagesLoading();
            return;

        }
        picturesProcessedID.push(img.id);
        loadedCount = loadedCount + 1;

        setCounterImagesLoading();
        appendPicture(img);
        //break;
    }
    if (images.length === k) {
        ((loglevel >= 1) ? console.log(t() + " all images where appended to html. Reached end of list of images to append.") : null);
        clearCounterImagesLoading();
    }
}

function triggerZoomAfterImageDownload() {
    countDownloadedImages++;
    if (isImageDownloadFinished()) {
        ((loglevel >= 1) ? console.log(t() + " zoom was triggered after download completetd.") : null);
        zoomLastPictures();
    }
}

function isImageDownloadFinished() {
    let numberToDownload = picturesProcessedID.length;
    if (numberToDownload === countDownloadedImages) {
        ((loglevel >= 1) ? console.log(t() + " all " + numberToDownload + " images where downloaded.") : null);
        removeImagesNotLoaded();
        return true;
    }
    return false;
}

function animate_on() {
    document.getElementById("button_share_box").style.visibility = "visible";
    document.getElementById("button_share_box").disabled = true;
    $('#button_share_box').find('.fa').addClass("fa-spin").addClass("fa-fw");
    ((loglevel >= 1) ? console.log(t() + " animate on") : null);
}

function animate_off() {
    $('#button_share_box').find('.fa').removeClass("fa-spin").removeClass("fa-fw");
    document.getElementById("button_share_box").style.visibility = "hidden";
    $('#face-scroll-top-message').text("");
    $('#face-scroll-top-message').fadeOut();
    ((loglevel >= 1) ? console.log(t() + " animate off") : null);
}

function clearCounterImagesLoading() {
    loadedCount = 0;
    blockScrolledToEnd = false;
    $("#button_share_box_counter_download").html('<sub></sub>');
    ((loglevel >= 1) ? console.log(t() + " clear image counter shown to user") : null);
    animate_off();
}

function setCounterImagesLoading() {
    $("#button_share_box_counter_download").html('<sub>' + picturesProcessedID.length + "/" + images.length + '</sub>');
    $("#button_share_box").css({'color': 'green'});
    ((loglevel >= 1) ? console.log(t() + " set counter for images to load to " + picturesProcessedID.length + "/" + images.length) : null);
}

function clearCounterNamesSending() {
    $("#button_share_box_counter_upload").html('<sup></sup>');
    ((loglevel >= 1) ? console.log(t() + " clear counter of images to load") : null);
    animate_off();
}

function setCounterNamesSending() {
    $("#button_share_box_counter_upload").html('<sup>' + unsentNames.length + '</sup>');
    $("#button_share_box").css({'color': 'red'});
    ((loglevel >= 1) ? console.log(t() + " set counter names to send to " + unsentNames.length) : null);
}

function initDate(fromS, toS) {
    fromS = checkDateString(fromS);
    toS = checkDateString(toS);
    var today = new Date();
    if (fromS == "") {
        from = new Date(new Date().setDate(today.getDate() - 365));
    } else {
        from = new Date(fromS);
    }
    if (toS == "") {
        //        to = new Date(new Date().setDate(today.getDate() + 1));
        to = new Date();
    } else {
        to = new Date(toS);
    }
    document.querySelector("#face-date-from").valueAsDate = from;
    document.querySelector("#face-date-to").valueAsDate = to;
    ((loglevel >= 1) ? console.log(t() + " initial dates: from = " + from + " to = " + to) : null);
}

function checkDateString(s) {
    ((loglevel >= 1) ? console.log(t() + " check date = " + s) : null);
    var i = s.search(/\d\d\d\d-\d\d-\d\d/);
    ((loglevel >= 1) ? console.log(t() + " date check found index = " + i) : null);
    if (i < 0) {
        return "";
    }
    return s;
}

var observerEnd = new IntersectionObserver(function (entries) {
    if (entries[0].isIntersecting === true) {
        ((loglevel >= 0) ? console.log(t() + " scrolled to end") : null);
        if (!blockScrolledToEnd) {
            blockScrolledToEnd = true;
            ((loglevel >= 1) ? console.log(t() + " loading more images was NOT blocked") : null);
            appendNextPictures();
        } else {
            ((loglevel >= 1) ? console.log(t() + " loading more images WAS blocked") : null);
        }
    }
}, {threshold: [1]});


$(document).ready(function () {
    document.getElementById("faces_server_status").style.visibility = "hidden";
    document.getElementById("face-panel-remove-button").style.display = "none";
    loglevel = parseInt($("#faces_log_level").text());
    console.log(t() + " log level = " + loglevel);
    observerEnd.observe(document.querySelector("#face-scoll-end"));
    let splittees = window.location.pathname.split("/");
    if (splittees.length > 3) {
        let s = splittees[3];
        s = s.split("?")[0];
        if (s === "me") {
            // "/faces/nick/me"
            $("#face-edit-set-name").remove();
            $("#face-edit-set-unknown").remove();
        }
    }
    faceEditControls = $("#template-face-frame-edit-controls").html();
    $("#template-face-frame-edit-controls").remove();
    endPanel = $("#face-scroll-end").html();
    $("#face-scroll-end").remove();
    initDate("", "");
    channel_name = window.location.pathname.split("/")[2];  // "/faces/nick/"
    channel_name = channel_name.split("?")[0];
    url_addon = window.location.origin + "/" + window.location.pathname.split("/")[1] + "/" + channel_name;
    postDetectAndRecognize();
});
