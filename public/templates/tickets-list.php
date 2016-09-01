<?php
$event_key = EM_Seatsio::event_key($EM_Event->post_id);
$event_data = EM_Seatsio::event_details($EM_Event->post_id);
?>
<script>
var event_json_data = '<?=json_encode($event_data);?>';
</script>
<div class="em-seatsio-tickets-chart clearfix">

	<div id="seatsio-chart" data-event="<?=$event_key?>"></div>

	<div id="seats-selected"></div>

</div>