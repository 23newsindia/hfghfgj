"use strict";

jQuery(function($) {
	const context = $('.cmb2-options-page');

	function conditionalGroups() {
		const targets = $('[name=remove_css], [name=allow_css_conditionals]', context);

		targets.on('change', function() {
			const valid = $('[name=remove_css]:checked, [name=allow_css_conditionals]:checked', context).length === 2;
			const target = $('.cmb2-id-allow-conditionals-data');
			
			valid ? target.show() : target.hide();
		});
		
		targets.trigger('change');
	}

	function multiCheckAllOption() {
		const targets = $('.cmb2-checkbox-list input[value=all]', context);
		targets.on('change', function() {

			const parent = $(this).closest('ul');
			const others = parent.find('input:not([value=all])');
			
			if ($(this).is(':checked')) {
				others.prop('disabled', true).parent('li').hide();
			}
			else {
				others.prop('disabled', false).parent('li').show();
			}
		});

		targets.trigger('change');
	}

	conditionalGroups();
	multiCheckAllOption();
});