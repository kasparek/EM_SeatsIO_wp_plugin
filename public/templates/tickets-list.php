<?php
$event_key = EM_Seatsio::event_key($EM_Event->post_id);
$event_data = EM_Seatsio_ajax::event_details_json($EM_Event->post_id);

$EM_Tickets = $EM_Event->get_bookings()->get_tickets();
$collumns = $EM_Tickets->get_ticket_collumns();
?>
<script>
var event_json_data = '<?=$event_data;?>';
</script>
<div class="em-seatsio-tickets-chart clearfix">
<div id="seatsio-chart" data-event="<?=$event_key?>"></div>
<table>
<thead><tr><th>Selected tickets</th><th style="width: 100px;">Place</th><th style="width: 100px;">Price</th><th style="width: 40px;"></th></tr></thead>
<tbody id="seats-selected">
<tr><td colspan="4">Select Places to Book on Chart</td></tr>
</tbody>
<tfoot>
<tr>
<td></td>
<td style="text-align: right;">Total:</td>
<td id="seatsio-bookings-total" colspan="2">$0.00</td>
</tr>
</tfoot>
</table>
<?php foreach( $EM_Tickets->tickets as $EM_Ticket ): ?>
	<?php if( $EM_Ticket->is_displayable() ): ?>
		<input type="hidden" data-seatsio_category="<?=$EM_Ticket->ticket_meta['seatsio_category']?>" name="em_tickets[<?php echo $EM_Ticket->ticket_id ?>][spaces]" value="0" class="em-ticket-select" />
	<?php endif; ?>
<?php endforeach; ?>
<div id="em-seatsio-bookings"></div>
</div>