window.em_seatsio = {};
jQuery(document).ready(function($) {
    var event_data = typeof event_json_data !== 'undefined' ? JSON.parse(event_json_data) : null;
    var EM_Seatsio_Tickets = function() {
        var self = this;
        self.selected_objects = [];
        self.bookings_default = null;
        self.chart = null;
        self.getTicketBySeatsioCategory = function(category) {
            var result = null;
            $.each(event_data.tickets, function(index, ticket) {
                if (ticket.meta.seatsio_category == category) result = ticket;
            });
            return result;
        };
        self.moneyFormat = function(str) {
            var number = parseFloat(str) || 0;
            number = number.toFixed(2);
            number = '$' + number.toLocaleString();
            return number;
        };
        self.releaseObject = function(e) {
            var uuid = $(e.currentTarget).data('seat');
            self.chart.deselectObjects([uuid]);
            return false;
        };
        self.updateSelectedObjects = function() {
            $(".em-ticket-select").val('0');
            if (!self.bookings_default) {
                self.bookings_default = $("#seats-selected").html();
            }
            $("#seats-selected").html(self.bookings_default);
            $("#em-seatsio-bookings").html('');
            if (!self.selected_objects || self.selected_objects.length === 0) return;
            $("#seats-selected").html('');
            var labels = [];
            var total = 0;
            $.each(self.selected_objects, function(index, value) {
                var ticket_category = value.category.key;
                var $cat_input = $("input.em-ticket-select[data-seatsio_category=" + ticket_category + "]");
                $cat_input.val(parseInt($cat_input.val()) + 1);
                var ticket = self.getTicketBySeatsioCategory(value.category.key);
                if (ticket) {
                    total += parseFloat(ticket.price) || 0;
                    $("#seats-selected").append('<tr><td>' + ticket.name + '</td><td>' + value.label + '</td><td>' + self.moneyFormat(ticket.price) + '</td><td><a href="#" class="ab-item em-seatsio-booking-remove" data-seat="' + value.uuid + '"><span class="dashicons dashicons-no"></span></a></td></tr>');
                    $("#em-seatsio-bookings").append('<input type="hidden" name="em_seatsio_bookings[' + value.uuid + ']" value="' + value.label + '">');
                }
            });
            $(".em-seatsio-booking-remove").on('click', self.releaseObject);
            $("#chart-selected").html(labels.join("\n"));
            $("#seatsio-bookings-total").text(self.moneyFormat(total));
        };
        self.isObjectSelected = function(object) {
            var ret = false;
            $.each(self.selected_objects, function(index, value) {
                if (object.id === value.id) ret = true;
            });
            return ret;
        };
        self.removeSelectedObject = function(object) {
            var list = [];
            $.each(self.selected_objects, function(index, value) {
                if (object.id !== value.id) list.push(value);
            });
            self.selected_objects = list;
        };
        self.init = function() {
            if ($(".em-seatsio-tickets-chart").length > 0) {
                jQuery.getScript('https://app.seats.io/chart.js', function() {
                    var isEditor = $("#seats-selected").length > 0;
                    var chartConfig = {
                        divId: "seatsio-chart",
                        publicKey: em_seatsio_object.seatsio_public_key,
                        event: $("#seatsio-chart").data('event'),
                        reserveOnSelect: isEditor,
                        onObjectClicked: function(object) {
                            if (event_data.seats[object.uuid].user_id) {
                                //show up the client modal popup
                                var b_user = event_data.booked_users[event_data.seats[object.uuid].user_id];
                                open_modal(b_user);
                            }
                        },
                        onObjectSelected: function(object) {
                            if (!isEditor) self.chart.deselectObjects([object.uuid]);
                            if (!self.isObjectSelected(object)) {
                                self.selected_objects.push(object);
                            }
                            self.updateSelectedObjects();
                        },
                        onObjectDeselected: function(object) {
                            if (self.isObjectSelected(object)) {
                                self.removeSelectedObject(object);
                            }
                            self.updateSelectedObjects();
                        },
                        tooltipText: function(object) {
                            return event_data.seats[object.uuid].publicLabel;
                        }
                    };
                    if(event_data.public) {
                        chartConfig.isObjectSelectable = function(object) {
                            return false;
                        };
                        chartConfig.objectColor = function(object, defaultColor) {
                            if (object.status === 'booked') {
                                return '#F06509';
                            }
                            return '#333333';
                        };
                    }
                    self.chart = new seatsio.SeatingChart(chartConfig).render();
                });
            }
        };
    };
    var EMS_tickets = new EM_Seatsio_Tickets();
    if (event_data) {
        EMS_tickets.init();
    } else {
        var post_id = null;
        var public = false;
        if ($("body").hasClass('single-event') && $("#em-booking").length > 0) {
            $("#em-booking").after('<div class="em-seatsio-tickets-chart"><div id="seatsio-chart"></div></div>');
            post_id = parseInt((document.body.className.match(/(?:^|\s)postid-([0-9]+)(?:\s|$)/) || [0, 0])[1]);
        } else if ($("div.em-seatsio-tickets-chart").length > 0) {
            post_id = $("div.em-seatsio-tickets-chart").data('event');
            public = true;
        }
        if (post_id) {
            jQuery.ajax({
                dataType: "json",
                url: em_seatsio_object.ajax_url,
                method: 'post',
                data: {
                    post_id: post_id,
                    public: public ? 1 : 0,
                    action: 'em_seatsio_get_event'
                },
                error: function(xhr, status, error) {
                    console.log(status);
                },
                success: function(response) {
                    if (response && response.event_key) {
                        event_data = response;
                        $("#seatsio-chart").data('event', response.event_key);
                        EMS_tickets.init();
                    }
                }
            });
        }
    }
    //modal
    var modal_bg = "<div class='modal-overlay js-modal-close'></div>";
    var modal = '<div id="profile-popup" class="modal-box"><header> <a href="#" class="js-modal-close close">×</a><h3>Modal Popup</h3></header>' + '<div class="modal-body"><img src="" class="avatar"><div class="profile-info"><p></p><a href="" class="profile" target="_blank">Full Profile</a></div><div class="clearfix"></div></div><footer> <a href="#" class="btn btn-small js-modal-close">Close</a> </footer></div>';
    $("body").append(modal);
    var open_modal = function(data) {
        $("body").append(modal_bg);
        $(".js-modal-close").off().on('click', function() {
            $(".modal-box, .modal-overlay").fadeOut(500, function() {
                $(".modal-overlay").remove();
            });
            return false;
        });
        $(".modal-overlay").css('height', $(document).height() + 'px').fadeTo(500, 0.7);
        $("#profile-popup h3").html(data.display_name);
        $("#profile-popup p").html(data.shortbio ? data.shortbio : '');
        $("#profile-popup img.avatar").hide().one('load', function() {
            $(this).fadeIn();
        }).attr('src', data.profile_photo_url ? data.profile_photo_url : "");
        $("#profile-popup a.profile").attr('href', data.permalink ? data.permalink : "#");
        $(window).resize();
        var sc = $(document).scrollTop();
        $('#profile-popup').fadeIn(500).css('top', sc + 200);
    };
    //center modal
    $(window).resize(function() {
        $(".modal-box").css({
            top: ($(window).height() - $(".modal-box").outerHeight()) / 2,
            left: ($(window).width() - $(".modal-box").outerWidth()) / 2
        });
    }).resize();
});