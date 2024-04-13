(function ($) {
	var field_all_day = $('#event_all_day'),
		field_start_hour = $('#event_start_hour'),
		field_start_minute = $('#event_start_minute'),
		field_end_hour = $('#event_end_hour'),
		field_end_minute = $('#event_end_minute'),
		all_day_change = function () {
			if (field_all_day.prop('checked')) {
				field_start_hour.attr('disabled', 'disabled');
				field_start_minute.attr('disabled', 'disabled');
				field_end_hour.attr('disabled', 'disabled');
				field_end_minute.attr('disabled', 'disabled');
			} else {
				field_start_hour.removeAttr('disabled');
				field_start_minute.removeAttr('disabled');
				field_end_hour.removeAttr('disabled');
				field_end_minute.removeAttr('disabled');
			}
		};
	field_all_day.change(all_day_change);
	all_day_change();

	$('.c-picker').wpColorPicker({
		defaultColor: false,
		change: function (event, ui) { },
		clear: function () { },
		hide: true,
		palettes: true,
	});
})(jQuery);
