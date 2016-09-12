truncate

truncate wp_em_tickets_bookings;
truncate wp_em_seatsio_bookings;

truncate wp_em_events;
truncate wp_em_tickets;
truncate wp_em_seatsio_location;
truncate wp_em_seatsio_event;
delete from wp_postmeta where post_id in (select id from wp_posts where post_type='event');
delete from wp_posts where post_type='event';
delete from wp_postmeta where post_id in (select id from wp_posts where post_type='event-recurring');
delete from wp_posts where post_type='event-recurring';