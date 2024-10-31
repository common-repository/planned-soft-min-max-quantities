(function($){
  var moduleSwitchRequest = function(data, btn){
    $.post(plannedsoftCore.ajaxUrl, data)
    .done(function(r){
      if (r.success && r.nonce) {
        btn.data('nonce', r.nonce);
      }

      if (r.success) {
        if (data.action === 'plannedsoft_core_activate_module') {
          $.post(plannedsoftCore.ajaxUrl, {slug: data.slug, action: 'plannedsoft_core_module_footer'})
            .done(function(r){
              btn.parent('.footer').html(r.footer);
            })
            .complete(function(){
              btn.removeClass('loading');
              $('.plannedsoft-core-module-switch').removeClass('loading');
            });
        } else {
          btn.parent('.footer').html(r.footer);
        }
      }

      if (! r.success && r.message) {
        $('.plannedsoft-core-module-switch').removeClass('loading');
        btn.toggleClass('switch-on switch-off');
        alert(r.message);
      }
    })
    .complete(function(){
      // $('.plannedsoft-core-module-switch').removeClass('loading');
    });
  };

  var moduleInstallRequest = function(data, btn){
    $.post(plannedsoftCore.ajaxUrl, data)
    .done(function(r){
      if (r.success) {
        $.post(plannedsoftCore.ajaxUrl, {slug: data.slug, action: 'plannedsoft_core_module_footer'})
        .done(function(r){
          btn.parent('.footer').html(r.footer);
        })
        .complete(function(){
          btn.removeClass('loading');
        });
      } else if (r.data.errorMessage) {
        btn.removeClass('loading');
        alert(r.data.errorMessage);
      }
    })
    .complete(function(){
      $('.install-btn').not(btn).removeClass('loading');
    })
  };

  $(document).ready(function(){
    $(document).on('click', '.install-btn', function(e){
      e.preventDefault;
      var btn = $(this);

      if (btn.hasClass('loading')) {
        return false;
      }

      $('.install-btn').addClass('loading');

      moduleInstallRequest({
        'slug': btn.data('slug'),
        'action': 'install-plugin',
        '_wpnonce': btn.data('nonce')
      }, btn);
      return false;
    });

    $(document).on('click', '.plannedsoft-core-module-switch', function(e){
      e.preventDefault;
      var btn = $(this);

      if (btn.hasClass('loading')) {
        return false;
      }

      $('.plannedsoft-core-module-switch').addClass('loading');

      if ( btn.hasClass('switch-on') ) {
        btn.toggleClass('switch-on switch-off');
        moduleSwitchRequest({
          'slug': btn.data('slug'),
          'action': 'plannedsoft_core_deactivate_module',
          'nonce': btn.data('nonce')
        }, btn);
      } else {
        btn.toggleClass('switch-on switch-off');
        moduleSwitchRequest({
          'slug': btn.data('slug'),
          'action': 'plannedsoft_core_activate_module',
          'nonce': btn.data('nonce')
        }, btn);
      }
      return false;
    });
  });
})(jQuery);
