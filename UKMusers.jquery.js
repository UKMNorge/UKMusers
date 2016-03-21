jQuery(document).ready(function() {
	jQuery("#readMore1").click(function() {
		if(jQuery("#readMore1").html() == "Les mer") {
			jQuery("#readMore1Text").show();
			jQuery("#readMore1").html("Skjul tekst");
		} else {
			jQuery("#readMore1").html("Les mer");
			jQuery("#readMore1Text").hide();
		}
	});
});