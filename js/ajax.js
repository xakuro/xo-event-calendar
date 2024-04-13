xo_event_calendar_month = function (e, month, event, categories, holidays, prev, next, start_of_week, months, navigation, title_format, is_locale, columns, base_month) {
	var target = e.closest('.xo-event-calendar');
	var id = target.getAttribute('id');
	var request = new XMLHttpRequest();

	target.setAttribute('disabled', 'disabled');
	target.classList.add('xo-calendar-loading');

	request.onreadystatechange = function () {
		if (request.readyState === 4) {
			if (200 <= request.status && request.status < 300) {
				target.classList.remove('xo-calendar-loading');
				target.getElementsByClassName('xo-months')[0].innerHTML =
					request.response;
			}
		}
	};

	request.open('POST', xo_event_calendar_object.ajax_url, true);
	request.setRequestHeader(
		'content-type',
		'application/x-www-form-urlencoded; charset=UTF-8'
	);
	request.send('action=' + xo_event_calendar_object.action +
		'&id=' + id +
		'&month=' + month +
		'&event=' + event +
		'&categories=' + categories +
		'&holidays=' + holidays +
		'&prev=' + prev +
		'&next=' + next +
		'&start_of_week=' + start_of_week +
		'&months=' + months +
		'&navigation=' + navigation +
		'&title_format=' + title_format +
		'&is_locale=' + is_locale +
		'&columns=' + columns +
		'&base_month=' + base_month
	);

	return false;
};

xo_simple_calendar_month = function (e, month, holidays, prev, next, start_of_week, months, navigation, title_format, is_locale, columns, caption_color, caption_bgcolor, base_month) {
	var target = e.closest('.xo-simple-calendar');
	var id = target.getAttribute('id');
	var request = new XMLHttpRequest();

	target.setAttribute('disabled', 'disabled');
	target.classList.add('xo-calendar-loading');

	request.onreadystatechange = function () {
		if (request.readyState === 4) {
			if (200 <= request.status && request.status < 300) {
				target.classList.remove('xo-calendar-loading');
				target.getElementsByClassName('calendars')[0].innerHTML = request.response;
			}
		}
	};

	request.open('POST', xo_simple_calendar_object.ajax_url, true);
	request.setRequestHeader(
		'content-type',
		'application/x-www-form-urlencoded; charset=UTF-8'
	);
	request.send('action=' + xo_simple_calendar_object.action +
		'&id=' + id +
		'&month=' + month +
		'&holidays=' + holidays +
		'&prev=' + prev +
		'&next=' + next +
		'&start_of_week=' + start_of_week +
		'&months=' + months +
		'&navigation=' + navigation +
		'&title_format=' + title_format +
		'&is_locale=' + is_locale +
		'&columns=' + columns +
		'&caption_color=' + caption_color +
		'&caption_bgcolor=' + caption_bgcolor +
		'&base_month=' + base_month
	);

	return false;
};
