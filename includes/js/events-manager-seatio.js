jQuery(document).ready(function($) {

var event_data = typeof event_json_data !== 'undefined' ? JSON.parse( event_json_data ) : null;

    var EM_Seatsio_Tickets = function() {
        var self = this;
        self.selected_objects = [];
        self.updateSelectedObjects = function() {

            //hidden input name - name="em_tickets[48][spaces]" - value = [int] of seats
            
            $("#seats-selected").text('');
            if (!self.selected_objects || self.selected_objects.length === 0) return;
            var labels = [];
            $.each(self.selected_objects, function(index, value) {
                console.log(value);
                /*var line = '<input type="hidden" name="em_tickets[48][spaces]" value="'++'" />'+"\n";
                line += '<input type="hidden" name="em_tickets[48][seats]" value="'++'" />'+"\n";
                labels.push(value.objectType + ' ' + value.label);
                */
            });
            $("#chart-selected").html(labels.join("\n"));
            
        };
        self.isObjectSelected = function(object) {
            var ret = false;
            $.each(self.selected_objects, function(index, value) {
                if(object.id === value.id) ret = true;
            });
            return ret;
        };
        self.removeSelectedObject = function(object) {
            var list = [];
            $.each(self.selected_objects, function(index, value) {
                if(object.id !== value.id) list.push(value);
            });
            self.selected_objects = list;
        };
        self.init = function() {
            if ($(".em-seatsio-tickets-chart").length > 0) {
                jQuery.getScript('https://app.seats.io/chart.js', function() {
                    var chart = new seatsio.SeatingChart({
                        divId: "seatsio-chart",
                        publicKey: ajax_object.seatsio_public_key,
                        event: $("#seatsio-chart").data('event'),
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
                            var str;
                            switch(object.status) {
                                case 'blocked':
                                    str = 'Reserved';
                                    break;
                                case 'booked':
                                    str = 'booked'; //TODO: get infor about customer
                                    break;
                                default:
                                    console.log(object);
                                    str = 'Available: '+object.category.label;
                            }
                            return str;
                        }
                    }).render();
                });
            }
        };
    };

    if(event_data) {
        var EMS_tickets = new EM_Seatsio_Tickets();
        EMS_tickets.init();
    }
});