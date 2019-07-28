'use strict';

jQuery(document).ready(function ($) {
  wooco_active_settings();
  wooco_arrange();

  $('#product-type').on('change', function () {
    wooco_active_settings();
  });

  $('body').on('click', '.wooco_add_component', function (e) {
    var components = $('.wooco_component').length;
    var data = {
      action: 'wooco_add_component',
      count: components
    };
    $.post(ajaxurl, data, function (response) {
      $('.wooco_components tbody').append(response);
      wooco_arrange();
    });
    e.preventDefault();
  });

  $('body').on('click', '.wooco_remove_component', function (e) {
    $(this).closest('.wooco_component').remove();
    e.preventDefault();
  });

  $('body').on('click', '.wooco_component_heading', function () {
    $(this).closest('.wooco_component_inner').toggleClass('active');
  });

  $('body').on('change, keyup', '.wooco_input_name', function () {
    var _val = $(this).val();
    $(this).closest('.wooco_component_inner').find('.wooco_component_name').html(_val);
  });

  $('.wooco_component_type').each(function () {
    var _val = $(this).val();
    $(this).closest('.wooco_component_content_line_value').find('.wooco_hide').hide();
    $(this).closest('.wooco_component_content_line_value').find('.wooco_show_if_' + _val).show();
  });

  $('body').on('change', '.wooco_component_type', function () {
    var _val = $(this).val();
    $(this).closest('.wooco_component_content_line_value').find('.wooco_hide').hide();
    $(this).closest('.wooco_component_content_line_value').find('.wooco_show_if_' + _val).show();
  });

  function wooco_arrange() {
    $('.wooco_components .wooco_component').arrangeable({
      dragSelector: '.wooco_move_component'
    });
  }

  function wooco_active_settings() {
    if ($('#product-type').val() == 'composite') {
      $('li.general_tab').addClass('show_if_composite');
      $('#general_product_data .pricing').addClass('show_if_composite');
      $('.composite_tab').addClass('active');
      $('#_downloadable').closest('label').addClass('show_if_composite').removeClass('show_if_simple');
      $('#_virtual').closest('label').addClass('show_if_composite').removeClass('show_if_simple');
      $('.show_if_external').hide();
      $('.show_if_simple').show();
      $('.show_if_composite').show();
      $('.product_data_tabs li').removeClass('active');
      $('.panel-wrap .panel').hide();
      $('#wooco_settings').show();
    } else {
      $('li.general_tab').removeClass('show_if_composite');
      $('#general_product_data .pricing').removeClass('show_if_composite');
      $('#_downloadable').closest('label').removeClass('show_if_composite').addClass('show_if_simple');
      $('#_virtual').closest('label').removeClass('show_if_composite').addClass('show_if_simple');
      $('.show_if_composite').hide();
    }
  }
});
