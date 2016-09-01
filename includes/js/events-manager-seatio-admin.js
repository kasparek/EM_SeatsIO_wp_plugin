jQuery(document).ready(function($) {
    var EM_Seatsio_Event = function() {
        var self = this;
        self.selected_objects = [];
        self.updateSelectedObjects = function() {
            $("#chart-selected").text('');
            if (!self.selected_objects || self.selected_objects.length === 0) return;
            var labels = [];
            $.each(self.selected_objects, function(index, value) {
                labels.push(value.objectType + ' ' + value.label);
            });
            $("#chart-selected").text(labels.join(', '));
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
            if (response.seats && response.seats.blocked) {
                var labels = [];
                $.each(response.seats.blocked, function(index, value) {
                    labels.push(value.objectType + ' ' + value.label);
                });
                $("#chart-blocked").text(labels.join(', '));
            }
        };
        self.init = function() {
            var post_id = parseInt($("input[type=hidden][name=post_ID]").val()) || 0;
            jQuery.ajax({
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
                    if (response && response.event_key) {
                        $("div#em-event-where").before('<div id="em-event-seatsio-chart" class="postbox"><h2 class="hndle">Seats.io Chart</h2><div class="inside"><div id="seatsio-chart"></div>' + '<div><label>Blocked:</label><span id="chart-blocked"></span></div><div><label>Selected:</label><span id="chart-selected"></span></div><div>' + '<label>Fake Reservation:</label> <button class="button" id="seatsio_btn_block">Block Selected</button> <button class="button" id="seatsio_btn_release">Release Selected</button>' + '</div>' + '</div></div>');
                        jQuery.getScript('https://app.seats.io/chart.js', function() {
                            var chart = new seatsio.SeatingChart({
                                divId: "seatsio-chart",
                                publicKey: response.public_key,
                                event: response.event_key,
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
                                objectColor: function(object, defaultColor, extraConfig) {
                                    if (object.status === 'blocked') {
                                        return '#888888';
                                    }
                                    return defaultColor;
                                },
                                tooltipText: function(object) {
                                    if (object.status === 'blocked') {
                                        return 'Blocked';
                                    }
                                    return '';
                                },
                                isObjectSelectable: function(object) {
                                    if (object.status === 'free') return true;
                                    if (object.status === 'blocked') return true;
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
                $("#em_seatsio_chart_select_thumb").html('<img src="' + thumb + '" style="max-height: 100px;">');
            }
        };
        self.init = function() {
            if ($("#em-location-data").length > 0) {
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
                            $("#em-location-data table").append('<tr><th>Seatsio Chart:</th><td><select id="em_seatsio_chart_select" name="em_seatsio_chart"><option value="">Select Seats.io Chart</option></select></td></tr>');
                            $("#em_seatsio_chart_select").after('<div id="em_seatsio_chart_select_thumb" style="text-align: center;"></div>');
                        }
                        $.each(response.list, function(index, value) {
                            $("#em_seatsio_chart_select").append('<option value="' + value.key + '" data-thumb="' + value.thumb + '"' + (selected_key == value.key ? ' selected="selected"' : '') + '>' + value.name + '</option>');
                        });
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
                                                if(seats > 0) {
                                                $("a#em-tickets-add").trigger('click');
                                                var tbody = $("#em-tickets-form table.form-table tbody:last")[0];
                                                var $ticket_name_input = $("input.ticket_name", tbody);
                                                $ticket_name_input.val(value.label);
                                                var name = $ticket_name_input.attr('name');
                                                $ticket_name_input.after('<input type="hidden" name="' + name.replace('ticket_name', 'ticket_meta_seatsio_category') + '" class="ticket_meta_category" value="' + value.key + '"/>');
                                                $ticket_name_input.after('<input type="hidden" name="' + name.replace('ticket_name', 'ticket_meta_seatsio_chart') + '" class="ticket_meta_chart" value="' + chart_key + '"/>');
                                                var text = value.label,
                                                    regex = /\$\s*[0-9,.]+(?:\s*\.\s*\d{2})?/g,
                                                    match = text.match(regex);
                                                if (match) {
                                                    match = match[0].replace(/\s/g, "").replace('$', '');
                                                } else match = 0;
                                                $("input.ticket_price",tbody).val(match);
                                                $("input.ticket_spaces",tbody).val(seats);
                                                $("span.ticket_available_spaces",tbody).text(seats);
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
});