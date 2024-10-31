(function($){

    function showhide_roles_dropdown(elem){
        if(elem.val() == 'roles'){
            elem.parent().parent().parent().find('#woocommerce_minmax_applies_to_roles').parent().parent().show();
        }
        else {
            elem.parent().parent().parent().find('#woocommerce_minmax_applies_to_roles').parent().parent().hide();
        }
    }

    $(document).ready(function(){

        // tooltip
        if ($.fn.tipTip) {
            $('.woocommerce-help-tip').tipTip({
                'attribute': 'data-tip',
                'fadeIn':    50,
                'fadeOut':   50,
                'delay':     0
            });
        }
        

        // convert to applies to multiselect into select2
        if ($.fn.select2) {
            $('.woocommerce_minmax_applies_to_roles_multiselect').select2();
        }

        // for global level settings page
        if ($.fn.select2) {
            $('.multiselect').select2();
        }
        

        // Show/hide roles multiselect based on applies to dropdown (previously saved value)
        $('.woocommerce_minmax_applies_to').each(function(){
            showhide_roles_dropdown($(this));
        });

        // Show/hide roles multiselect based on applies to dropdown (on change)
        $(document).on('change', '.woocommerce_minmax_applies_to', function(){
            showhide_roles_dropdown($(this));
        });

        // on change the value of apply to dropdown of edit-product page
        $(document).on('change', '#product_minmax_apply_to', function(){
            if($(this).val() == 'roles'){
                $(this).parent().parent().find('#roles-section').show();
            }
            else {
                $(this).parent().parent().find('#roles-section').hide();
            }
        });

        // on click close button of edit product page
        $(document).on('click', '.single-minmax-close', function(){
            $(this).parent().remove();
        });

        $(document).on('click', '.single-global-minmax-close', function(){
            var ruleid = $(this).parent().attr('id').replace('minmax-rule-', ''); //this rule id will be appended to the hidden field for being deleted
            var existing_rule_id = JSON.parse($('.rules_to_delete').val());

            // append rule_id into existing rule id
            existing_rule_id.push(ruleid);

            // reset the hidden field value again
            $('.rules_to_delete').val(JSON.stringify(existing_rule_id));

            
            $(this).parent().remove();
        });

        // jquery ui sortable for edit product page
        // $(".minmax-section-wrapper").sortable(
        //     {
        //         //handle: 'h4.first',
        //         containment: 'parent',
        //         axis: 'y'
        //     }
        // );

        // ajax add new rule (product level)
        $(document).on('click', 'button.addnew-minmax', function(){
            // get the last id
            var rule_id = parseInt($('.single-minmax-section').last().attr('id').replace('minmax-rule-', ''));
            
            // increment by 1 for new rule id
            rule_id += 1;

            // send ajax with new rule_id
            var data = {
                'action': 'add_new_minmax_rule',
                'rule_id': rule_id
            };

            $.post(ajaxurl, data, function(response) {
                var newElement = $(response);
                newElement.insertBefore('.addnew-minmax');
                // convert to applies to multiselect into select2
                if ($.fn.select2) {
                    newElement.find('.multiselect').select2();
                }
            });
        });

        // ajax add new rule (global cart level)
        $(document).on('click', 'button.addnew-global-minmax', function(){
            // get the last id
            var rule_id = parseInt($('.single-minmax-section').last().attr('id').replace('minmax-rule-', ''));
            
            // increment by 1 for new rule id
            rule_id += 1;

            // send ajax with new rule_id
            var data = {
                'action': 'add_new_global_minmax_rule',
                'rule_id': rule_id
            };

            $.post(ajaxurl, data, function(response) {
                var newElement = $(response);
                newElement.insertBefore('.addnew-global-minmax');
                // convert to applies to multiselect into select2
                if ($.fn.select2) {
                    newElement.find('.multiselect').select2();
                }

                // tooltip
                if ($.fn.tipTip) {
                    $('.woocommerce-help-tip').tipTip({
                        'attribute': 'data-tip',
                        'fadeIn':    50,
                        'fadeOut':   50,
                        'delay':     0
                    });
                }
            });
        });
    });


})(jQuery);