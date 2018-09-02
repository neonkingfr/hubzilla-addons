        function channelrepShowModerateForm( id ) {
                $.get('/channelrep/' + id , function (data) {
                        $('#channelrepModal').html(data);
                }, null, 'html');
                $('#channelrepModal').modal();
        }

        function channelrepAdd() {
                $.post('/channelrep/', { 
                        security-token: $('#channelrepSecurityToken').val(),
                        channelrepId: $('#channelrepId').val(),
                        channelrepPoints: $('#channelrepPoints').val(),
                        channelrepAction: 1
                        }, function (data) {
                                $('#channelrepModal').modal('hide');
                });
        function channelrepSubtract() {
                $.post('channelrep', { 
                        security-token: $('#channelrepSecurityToken').val(),
                        item: $('#channelrepId').val(),
                        points: $('#channelrepPoints').val(),
                        channelrepAction: -1
                        }, function (data) {
                                $('#channelrepModal').modal('hide');
                });
