<?php
/**
 * FAQ & Support page
 */
?>
<style>
	.faq-ts-accordion {
		background-color: #eee;
		color: #444;
		cursor: pointer;
		padding: 18px;
		width: 100%;
		border: none;
		text-align: left;
		outline: none;
		font-size: 15px;
		transition: 0.4s;
		margin-top: 5px;
	}
	.active, .faq-ts-accordion:hover {
		background-color: #ccc; 
	}
	.faq-ts-accordion:after {
		content: '\002B';
		color: #777;
		font-weight: bold;
		float: right;
		margin-left: 5px;
	}
	.active:after {
		content: "\2212";
	}
	.panel {
		padding: 3px 18px;
		display: none;
		background-color: #eeeeee80;
		overflow: hidden;
		border-right: 2px solid #ccc;
		border-left: 2px solid #ccc;
		border-bottom: 2px solid #ccc;
		border-radius: 0px 0px 15px 15px;
	}
	.main-panel {
		width: 65% !important;
	}
	.support-panel {
		padding: 5px;
	}
	.dashicons-external {
		content: "\f504";
	}
	.dashicons-editor-help {
		content: "\f223";
		margin-right: 1%;
	}
	div.panel.show {
		display: block !important;
	}
	.panel > p {
		font-weight: 500;
	}

	@media ( max-width : 800px ){
		.main-panel {
			width: 100% !important;
		}  
	}

</style>

<div class="main-panel">
	<h3>Frequently Asked Questions for <?php echo $ts_plugin_name; ?> Plugin</h3>
	<button class="faq-ts-accordion"><span class="dashicons dashicons-editor-help"></span><strong><?php echo $ts_faq[1]['question']; ?></strong></button>
	<div class="panel">
		<p><?php echo $ts_faq[1]['answer']; ?></p>
	</div>

	<button class="faq-ts-accordion"><span class="dashicons dashicons-editor-help"></span><strong><?php echo $ts_faq[2]['question']; ?></strong></button>
	<div class="panel">
		<p><?php echo $ts_faq[2]['answer']; ?></p>
	</div>

	<button class="faq-ts-accordion"><span class="dashicons dashicons-editor-help"></span><strong><?php echo $ts_faq[3]['question']; ?></strong></button>
	<div class="panel">
		<p><?php echo $ts_faq[3]['answer']; ?></p>
	</div>

	<button class="faq-ts-accordion"><span class="dashicons dashicons-editor-help"></span><strong><?php echo $ts_faq[4]['question']; ?></strong></button>
	<div class="panel">
		<p><?php echo $ts_faq[4]['answer']; ?></p>
	</div>

	<button class="faq-ts-accordion"><span class="dashicons dashicons-editor-help"></span><strong><?php echo $ts_faq[5]['question']; ?></strong></button>
	<div class="panel">
		<p><?php echo $ts_faq[5]['answer']; ?></p>
	</div>

	<button class="faq-ts-accordion"><span class="dashicons dashicons-editor-help"></span><strong><?php echo $ts_faq[6]['question']; ?></strong></button>
	<div class="panel">
		<p><?php echo $ts_faq[6]['answer']; ?></p>
	</div>

	<button class="faq-ts-accordion"><span class="dashicons dashicons-editor-help"></span><strong><?php echo $ts_faq[7]['question']; ?></strong></button>
	<div class="panel">
		<p><?php echo $ts_faq[7]['answer']; ?></p>
	</div>

	<button class="faq-ts-accordion"><span class="dashicons dashicons-editor-help"></span><strong><?php echo $ts_faq[8]['question']; ?></strong></button>
	<div class="panel">
		<p><?php echo $ts_faq[8]['answer']; ?></p>
	</div>

	<button class="faq-ts-accordion"><span class="dashicons dashicons-editor-help"></span><strong><?php echo $ts_faq[9]['question']; ?></strong></button>
	<div class="panel">
		<p><?php echo $ts_faq[9]['answer']; ?></p>
	</div>
	<button class="faq-ts-accordion"><span class="dashicons dashicons-editor-help"></span><strong><?php echo $ts_faq[10]['question']; ?></strong></button>
	<div class="panel">
		<p><?php echo $ts_faq[10]['answer']; ?></p>
	</div>
</div>

<div class="support-panel">
	<p style="font-size: 19px">
		<i><strong><a href="https://www.tychesoftwares.com/faq-booking-appointment-plugin/" target="_blank">View All FAQs</a></strong></i>&emsp;&mdash;&emsp; 
		<i><strong><a href="https://www.tychesoftwares.com/docs/docs/booking-appointment-plugin-for-woocommerce/" target="_blank">Documentation</a></strong></i> 
	</p>
	<p style="font-size: 16px">
		<i>If your queries are not answered here, you can send an email directly to <strong><a href="mailto:support@tychesoftwares.freshdesk.com">support@tychesoftwares.freshdesk.com</a></strong> for some additional requirements.</i> 
	</p>
</div>
<script>
var acc = document.getElementsByClassName("faq-ts-accordion");
var i;

for (i = 0; i < acc.length; i++) {
	acc[i].onclick = function() {
		hideAll();

		this.classList.toggle("active");
		this.nextElementSibling.classList.toggle("show");
	}
}

function hideAll() {
	for (i = 0; i < acc.length; i++) {
		acc[i].classList.toggle( "active", false);
		acc[i].nextElementSibling.classList.toggle( "show", false );
	}
}
</script>
