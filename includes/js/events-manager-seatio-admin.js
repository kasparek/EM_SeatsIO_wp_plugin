jQuery(document).ready(function($) {
    var event_data = null;
    jQuery('#em-location-reset a').on('click', function(e) {
        jQuery('div.em-location-data select').prop('disabled', false);
    });
    var EM_Seatsio_Event = function() {
        var self = this;
        self.chart = null;
        self.selected_objects = [];
        self.bookings_default = null;
        self.moneyFormat = function(str) {
            var number = parseFloat(str) || 0;
            number = number.toFixed(2);
            number = '$' + number.toLocaleString();
            return number;
        };
        self.getTicketBySeatsioCategory = function(category) {
            var result = null;
            $.each(event_data.tickets, function(index, ticket) {
                if (ticket.meta.seatsio_category == category) result = ticket;
            });
            return result;
        };
        self.updateSelectedObjects = function() {
            var labels = [];
            if ($("#seatsio-bookings-total").length === 0) {
                $("#chart-selected").text('');
                if (!self.selected_objects || self.selected_objects.length === 0) return;
                $.each(self.selected_objects, function(index, value) {
                    labels.push(value.objectType + ' ' + value.label);
                });
                $("#chart-selected").text(labels.join(', '));
            } else {
                $(".em-ticket-select").val('0');
                if (!self.bookings_default) {
                    self.bookings_default = $("#seats-selected").html();
                }
                $("#seats-selected").html(self.bookings_default);
                $("#em-seatsio-bookings").html('');
                if (!self.selected_objects || self.selected_objects.length === 0) return;
                $("#seats-selected").html('');
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
            }
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
        self.updateSeats = function(response) {
            $("#chart-blocked").text('');
            if (response.seats) {
                var labels = [];
                $.each(response.seats, function(index, value) {
                    if (value.status == 'blocked') labels.push(value.objectType + ' ' + value.label);
                });
                $("#chart-blocked").text(labels.join(', '));
            }
        };
        self.get_seatsio_category_from_ticket = function(ticket_id) {
            var ret = null;
            $.each(event_data.tickets, function(index, row) {
                if (row.ticket_id == ticket_id) ret = row.meta.seatsio_category;
            });
            return ret;
        };
        self.get_seat_button = function(object) {
            return ' <a href="#" class="button seatsio-seat" data-uuid="' + object.uuid + '">' + object.label + '<input type="hidden" name="em_seatsio_bookings[' + object.uuid + ']" value="' + object.label + '"></a> ';
        };
        self.udpateSeatsListeners = function() {
            $("a.seatsio-seat").off().on('click', function() {
                if (self.is_editing_booking()) {
                    $(this).remove();
                    self.udpateSeatsListeners();
                } else {
                    alert('Please, click "Modify Booking" first.');
                }
                return false;
            });
            $('table.em-tickets-bookings-table tbody tr').each(function() {
                var num = $("a.seatsio-seat", this).length;
                $("input.em-ticket-select", this).val(num);
            });
        };
        self.is_editing_booking = function() {
            if ($("body").hasClass('event_page_events-manager-bookings') && $('.em-booking-single-edit').length > 0) {
                var d = $('.em-booking-single-edit').css('display');
                if (d === 'block') return true;
            }
            return false;
        };
        self.init = function(post_id) {
            if (!post_id) post_id = parseInt($("input[type=hidden][name=post_ID]").val()) || 0;
            if (post_id) jQuery.ajax({
                dataType: "json",
                url: ajaxurl,
                method: 'post',
                data: {
                    post_id: post_id,
                    action: 'em_seatsio_get_event'
                },
                error: function(xhr, status, error) {
                    console.log(status);
                },
                success: function(response) {
                    if (response && response.chart_changed && response.event && response.event.chartKey != response.db_chart_key) {
                        if (!response.db_chart_key) {
                            $("#wpbody-content h1").after('<div class="notice notice-error is-dismissible"><h1><strong>Location chart has changed. Current Location has no seats.io chart set.</strong> You will loose any existing bookings if you update the Event.</h1></div>');
                        } else {
                            $("#wpbody-content h1").after('<div class="notice notice-error is-dismissible"><h1><strong>Location chart has changed. Update Event to create Event Key and Ticket categories.</strong> You will loose any existing bookings.</h1></div>');
                        }
                        setTimeout(function() {
                            window.scrollTo(0, 0);
                        }, 500);
                    } else if (response && response.event_key) {
                        event_data = response;
                        if ($("body").hasClass('event_page_events-manager-bookings') && $('table.em-tickets-bookings-table').length > 0) {
                            $('table.em-tickets-bookings-table thead th:first').after('<th>Seats.io seats (click to remove from booking)</th>');
                            var booking_id = $("input[name=booking_id]").val();
                            if (event_data.bookings && event_data.bookings[booking_id]) {
                                var bookings = event_data.bookings[booking_id];
                            }
                            $('table.em-tickets-bookings-table tbody tr').each(function() {
                                var name = $("input.em-ticket-select", this).attr('name');
                                if (name) {
                                    var bookings_btns = [];
                                    name = parseInt(name.match(/\d+/)) || 0;
                                    if (bookings && bookings[name]) {
                                        var current_booking = bookings[name];
                                        $.each(current_booking, function(index, uuid) {
                                            bookings_btns.push(self.get_seat_button(event_data.seats[uuid]));
                                        });
                                    }
                                    bookings_btns = bookings_btns.join(' ');
                                    $("td.ticket-type", this).after('<td data-seatsio-category="' + self.get_seatsio_category_from_ticket(name) + '">' + bookings_btns + '</td>');
                                    self.udpateSeatsListeners();
                                }
                            });
                            $('table.em-tickets-bookings-table tfoot tr').each(function() {
                                $("th:first", this).after('<th></th>');
                            });
                        }
                        $("div#em-event-where").before('<div id="em-event-seatsio-chart" class="postbox"><h2 class="hndle">Seats.io Chart</h2><div class="inside"><div id="seatsio-chart"></div>' + '<div><label>Blocked:</label><span id="chart-blocked"></span></div><div><label>Selected:</label><span id="chart-selected"></span></div><div>' + '<label>Fake Reservation:</label> <button class="button" id="seatsio_btn_block">Block Selected</button> <button class="button" id="seatsio_btn_release">Release Selected</button>' + '</div>' + '</div></div>');
                        jQuery.getScript('https://app.seats.io/chart.js', function() {
                            var chart = new seatsio.SeatingChart({
                                divId: "seatsio-chart",
                                publicKey: response.public_key,
                                event: response.event_key,
                                extraConfig: event_data,
                                onObjectClicked: function(object) {
                                    if (event_data.seats[object.uuid].user_id) {
                                        //show up the client modal popup
                                        var b_user = event_data.booked_users[event_data.seats[object.uuid].user_id];
                                        open_modal(b_user);
                                    }
                                },
                                onObjectSelected: function(object) {
                                    if (self.is_editing_booking()) {
                                        $("td[data-seatsio-category=" + object.category.key + "]").append(self.get_seat_button(object));
                                        self.udpateSeatsListeners();
                                        return;
                                    }
                                    if (!self.isObjectSelected(object)) {
                                        self.selected_objects.push(object);
                                    }
                                    self.updateSelectedObjects();
                                },
                                onObjectDeselected: function(object) {
                                    if (self.is_editing_booking()) {
                                        $("a[data-uuid=" + object.uuid + "]").remove();
                                        self.udpateSeatsListeners();
                                        return;
                                    }
                                    if (self.isObjectSelected(object)) {
                                        self.removeSelectedObject(object);
                                    }
                                    self.updateSelectedObjects();
                                },
                                objectColor: function(object, defaultColor, extraConfig) {
                                    if(!extraConfig.seats[object.uuid].user_id && object.status==='booked') {
                                        return '#ff0000';
                                    }
                                    if (object.status === 'blocked') {
                                        return '#888888';
                                    }
                                    return defaultColor;
                                },
                                tooltipText: function(object) {
                                    if (event_data.seats) {
                                        return event_data.seats[object.uuid].publicLabel;
                                    }
                                },
                                isObjectSelectable: function(object,defaultValue, extraConfig) {
                                    if (object.status === 'free') return true;
                                    if (object.status === 'blocked') return true;
                                    if (!extraConfig.seats[object.uuid].user_id) return true;
                                    return false;
                                }
                            }).render();
                            var block_seats = function(status) {
                                if (self.selected_objects && self.selected_objects.length > 0) {
                                    var labels = [];
                                    $.each(self.selected_objects, function(index, value) {
                                        labels.push(value.id);
                                    });
                                    chart.clearSelection();
                                    self.selected_objects = [];
                                    setTimeout(function() {
                                        $("#chart-selected").text('Please wait...');
                                    }, 100);
                                    jQuery.ajax({
                                        dataType: "json",
                                        url: ajaxurl,
                                        method: 'post',
                                        data: {
                                            post_id: post_id,
                                            status: status,
                                            selected_objects: labels,
                                            action: 'em_seatsio_block_event'
                                        },
                                        error: function() {
                                            $("#chart-selected").text('');
                                        },
                                        success: function(response) {
                                            self.updateSeats(response);
                                            self.updateSelectedObjects();
                                        }
                                    });
                                }
                            };
                            self.chart = chart;
                            $("#seatsio_btn_release").on('click', function() {
                                block_seats('free');
                                return false;
                            });
                            $("#seatsio_btn_block").on('click', function() {
                                block_seats('blocked');
                                return false;
                            });
                        });
                        self.updateSeats(response);
                    }
                }
            });
        };
    };
    var EM_Seatsio_Location = function() {
        var self = this;
        self.current_chart_key = null;
        self.updateThumb = function() {
            var thumb = $("#em_seatsio_chart_select").find(":selected").data('thumb');
            if (thumb) {
                $("#em_seatsio_chart_select_thumb").html('<img src="' + thumb + '">');
            }
        };
        self.init = function() {
            if ($("#em-location-data").length > 0) {
                $(document).on('em_locations_autocomplete_selected', function(e) {
                    if (jQuery('div.em-location-data input').prop('readonly')) {
                        jQuery('div.em-location-data select').css('background-color', '#ccc').prop('disabled', true);
                    }
                });
                var post_id = parseInt($("input[type=hidden][name=post_ID]").val()) || 0;
                var location_id = parseInt($("input[type=hidden][name=location_id]").val()) || 0;
                jQuery.ajax({
                    dataType: "json",
                    url: ajaxurl,
                    method: 'post',
                    data: {
                        post_id: post_id,
                        location_id: location_id,
                        action: 'em_seatsio_get_charts'
                    },
                    error: function(xhr, status, error) {
                        console.log(status);
                    },
                    success: function(response) {
                        var charts_list = response.list;
                        var selected_key = response.selected;
                        if ($("#em_seatsio_chart_select").length > 0) {
                            $("#em_seatsio_chart_select option").remove();
                            $("#em_seatsio_chart_select_thumb").html('');
                        } else {
                            $("#em-location-data table").append('<tr><th>Seatsio Chart:</th><td><select id="em_seatsio_chart_select" name="em_seatsio_chart" style="width: 250px;"><option value="">Select Seats.io Chart</option></select></td></tr>');
                            $("#em_seatsio_chart_select").after('<div id="em_seatsio_chart_select_thumb" style="text-align:center; width:250px;"></div>');
                        }
                        $.each(response.list, function(index, value) {
                            $("#em_seatsio_chart_select").append('<option value="' + value.key + '" data-thumb="' + value.thumb + '"' + (selected_key == value.key ? ' selected="selected"' : '') + '>' + value.name + '</option>');
                        });
                        $(document).trigger('em_locations_autocomplete_selected');
                        $("#em_seatsio_chart_select").on('change', function() {
                            self.updateThumb();
                            var chart_key = $(this).val();
                            if (self.current_chart_key == chart_key) return false;
                            self.current_chart_key = chart_key;
                            if (chart_key.length > 0) {
                                jQuery.ajax({
                                    dataType: "json",
                                    url: ajaxurl,
                                    method: 'post',
                                    data: {
                                        chart_key: chart_key,
                                        action: 'em_seatsio_chart_details'
                                    },
                                    error: function(xhr, status, error) {
                                        console.log(status);
                                    },
                                    success: function(response) {
                                        var chart_details = response;
                                        var categories = chart_details.categories.list;
                                        var categorySums = {};
                                        if (chart_details.subChart.booths) {
                                            $.each(chart_details.subChart.booths, function(index, booth) {
                                                var key = booth.categoryKey ? booth.categoryKey : 0;
                                                if (!categorySums[key]) categorySums[key] = 1;
                                                else categorySums[key]++;
                                            });
                                        }
                                        $("a.ticket-actions-delete:not(:first)").each(function() {
                                            var tbody = $(this).closest('tbody')[0];
                                            if ($("input.ticket_meta_chart[value='" + chart_key + "']", tbody).length > 0) {
                                                //keep this ticket - is good for current chart
                                            } else {
                                                $(this).trigger('click');
                                            }
                                        });
                                        $.each(categories, function(index, value) {
                                            //check if ticket does not exist
                                            var has_ticket = false;
                                            if ($("input.ticket_meta_chart[value='" + chart_key + "']").length > 0) {
                                                if ($("input.ticket_meta_category[value=" + value.key + "]").length > 0) {
                                                    //we have this ticket
                                                    has_ticket = true;
                                                }
                                            }
                                            if (has_ticket === false) {
                                                var seats = categorySums[value.key] ? categorySums[value.key] : 0;
                                                if (seats > 0) {
                                                    $("a#em-tickets-add").trigger('click');
                                                    var tbody = $("#em-tickets-form table.form-table tbody:last")[0];
                                                    var $ticket_name_input = $("input.ticket_name", tbody);
                                                    var name = $ticket_name_input.attr('name');
                                                    $ticket_name_input.after('<input type="hidden" name="' + name.replace('ticket_name', 'ticket_meta_seatsio_category') + '" class="ticket_meta_category" value="' + value.key + '"/>');
                                                    $ticket_name_input.after('<input type="hidden" name="' + name.replace('ticket_name', 'ticket_meta_seatsio_chart') + '" class="ticket_meta_chart" value="' + chart_key + '"/>');
                                                    var text = value.label,
                                                        regex = /\$\s*[0-9,.]+(?:\s*\.\s*\d{2})?/g,
                                                        match = text.match(regex);
                                                    if (match) {
                                                        match = match[0].replace(/\s/g, "").replace('$', '');
                                                    } else match = 0;
                                                    value.label = value.label.replace(regex, '');
                                                    $ticket_name_input.val(value.label);
                                                    $("input.ticket_price", tbody).val(match);
                                                    $("input.ticket_spaces", tbody).val(seats);
                                                    $("span.ticket_available_spaces", tbody).text(seats);
                                                    $(".ticket-actions-edited", tbody).trigger('click');
                                                }
                                            }
                                        });
                                        $("a.ticket-actions-delete").hide();
                                        $("a#em-tickets-add").hide().after('Tickets are generated based on selected seats.io chart.');
                                        setTimeout(function() {
                                            $('html, body').stop(true);
                                        }, 100);
                                    }
                                });
                            }
                        }).trigger('change');
                    }
                });
            }
            $("#location-id").on('change', function() {
                self.init();
            });
        };
    };
    var EMS_location = new EM_Seatsio_Location();
    EMS_location.init();
    var EMS_event = new EM_Seatsio_Event();
    EMS_event.init();
    //events-manager-bookings
    //validate seats event exists
    if ($("body").hasClass('event_page_events-manager-bookings')) {
        var event_id = $("input[name=event_id]").val();
        if (event_id) {
            jQuery.ajax({
                dataType: "json",
                url: ajaxurl,
                method: 'post',
                data: {
                    event_id: event_id,
                    action: 'em_seatsio_has_event'
                },
                error: function(xhr, status, error) {
                    console.log(status);
                },
                success: function(response) {
                    if (response) {
                        if (response.success === true) {
                            console.log('We have event key', response.event_key);
                            //lets show the chart
                            //name="booking_comment"
                            if ($("div#em-booking-notes").length > 0) {
                                $("div#em-booking-notes").before('<div id="em-event-where"></div>');
                                EMS_event.init(response.post_id);
                            } else if ($(".em-bookings-table").length > 0) {
                                $(".em-bookings-table").after('<div style="margin-top:20px;">&nbsp;</div><div id="em-event-where"></div>');
                                EMS_event.init(response.post_id);
                            } else if (!EMS_event.chart) {
                                EMS_event.init(response.post_id);
                            }
                        } else {
                            console.log('Not event key');
                            $("#wpbody-content h1").after('<div class="notice notice-error is-dismissible"><p><strong>Seats.io Event Key not set for this event. Update Event to create Event Key.</strong></p></div>');
                        }
                    }
                }
            });
        }
    }
    //modal
    var modal_bg = "<div class='modal-overlay js-modal-close'></div>";
    var modal = '<div id="profile-popup" class="modal-box"><header> <a href="#" class="js-modal-close close">×</a><h3>Modal Popup</h3></header>' + '<div class="modal-body"><img src="" class="avatar"><div class="profile-info"><p></p><a href="" class="profile">Full Profile</a></div><div class="clearfix"></div></div><footer> <a href="#" class="btn btn-small js-modal-close">Close</a> </footer></div>';
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
        $("#profile-popup img.avatar").hide().one('load',function(){$(this).fadeIn();}).attr('src', data.profile_photo_url ? data.profile_photo_url : "");
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