let channel_name = "";

function correctLinks() {
    $('.link_correction').each(function (i, obj) {
        path = window.location.pathname;
        path = path.substr(0, path.lastIndexOf("/help"));
        link = obj.href;
        link = link.replace("channel-nick", channel_name);
        obj.href = link;
    });
}

function correctDomainName() {
    $('.domainname').each(function (i, obj) {
        let domain = window.location.origin;
        let s = domain.split("/")[2];
        obj.textContent = s;
    });
}

function correctBaseUrl() {
    $('.baseurl').each(function (i, obj) {
        let url = window.location.origin;
        obj.textContent = url;
    });
}

function correctWebName() {
    $('.webname').each(function (i, obj) {
        obj.textContent = channel_name;
    });
}

function correctWebdavUrl() {
    $('.webdavurl').each(function (i, obj) {
        let url = window.location.origin + "/dav/" + channel_name;
        obj.textContent = url;
    });
}

function correctWebdavUrlLinux() {
    $('.webdavurllinux').each(function (i, obj) {
        let url = window.location.hostname + "/dav/" + channel_name;
        obj.textContent = "davs://" + url;
    });
}

function correctAddonUrl() {
    $('.addonurl').each(function (i, obj) {
        let url = window.location.origin + "/faces/" + channel_name;
        obj.textContent = url;
    });
}

$(document).ready(function () {
    channel_name = window.location.pathname.split("/")[2];
    channel_name = channel_name.split("?")[0];
    correctLinks();
    correctDomainName();
    correctBaseUrl();
    correctWebName();
    correctWebdavUrl();
    correctWebdavUrlLinux();
    correctAddonUrl();
}
);

