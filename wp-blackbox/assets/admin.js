(function () {
	'use strict';

	document.addEventListener('toggle', function (event) {
		if (!event.target.matches('.wp-blackbox-event') || !event.target.open) {
			return;
		}

		document.querySelectorAll('.wp-blackbox-event[open]').forEach(function (details) {
			if (details !== event.target) {
				details.removeAttribute('open');
			}
		});
	}, true);
}());
