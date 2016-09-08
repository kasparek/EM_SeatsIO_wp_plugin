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
            $("#em-seatsio-bookings").html();
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
                    self.chart = new seatsio.SeatingChart({
                        divId: "seatsio-chart",
                        publicKey: em_seatsio_object.seatsio_public_key,
                        event: $("#seatsio-chart").data('event'),
                        reserveOnSelect: true,
                        onObjectClicked: function(object) {
                            if (event_data.seats[object.uuid].user_id) {
                                //show up the client modal popup
                                console.log('Show popup for user', event_data.seats[object.uuid].user_id);
                            }
                        },
                        onObjectSelected: function(object) {
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
                    }).render();
                });
            }
        };
    };
    var EMS_tickets = new EM_Seatsio_Tickets();
    if (event_data) {
        EMS_tickets.init();
    } else {
        if($("body").hasClass('single-event') && $("#em-booking").length>0) {
            $("#em-booking").after('<div class="em-seatsio-tickets-chart"><div id="seatsio-chart"></div></div>');
            var post_id = parseInt( ( document.body.className.match( /(?:^|\s)postid-([0-9]+)(?:\s|$)/ ) || [ 0, 0 ] )[1] );
            jQuery.ajax({
                dataType: "json",
                url: em_seatsio_object.ajax_url,
                method: 'post',
                data: {
                    post_id: post_id,
                    action: 'em_seatsio_get_event'
                },
                error: function(xhr, status, error) {
                    console.log(status);
                },
                success: function(response) {
                    if (response && response.event_key) {
                        event_data = response;
                        $("#seatsio-chart").data('event',response.event_key);
                        EMS_tickets.init();
                    }
                }
            });
        }
    }
});