<?php

if( !defined( 'ABSPATH' ) ) exit;  // Exit if accessed directly

if( ! class_exists( 'WPGV_Voucher_Taxonomy_Image' ) ) {

class WPGV_Voucher_Taxonomy_Image {
    
    public function __construct() {
     //
    }

    /**
     * Initialize the class and start calling our hooks and filters
     */
    public function init() {
     // Image actions
     add_action( 'wpgv_voucher_category_add_form_fields', array( $this, 'add_category_image' ), 10, 2 );
     add_action( 'created_wpgv_voucher_category', array( $this, 'save_category_image' ), 10, 2 );
     add_action( 'wpgv_voucher_category_edit_form_fields', array( $this, 'update_category_image' ), 10, 2 );
     add_action( 'edited_wpgv_voucher_category', array( $this, 'updated_category_image' ), 10, 2 );
     add_action( 'admin_enqueue_scripts', array( $this, 'load_media' ) );
     add_action( 'admin_footer', array( $this, 'add_script' ) );
   }

   public function load_media() {
     if( ! isset( $_GET['taxonomy'] ) || $_GET['taxonomy'] != 'wpgv_voucher_category' ) {
       return;
     }
     wp_enqueue_media();
   }
  
   /**
    * Add a form field in the new category page
    * @since 1.0.0
    */
  
   public function add_category_image( $taxonomy ) { ?>
     <div class="form-field term-group">
       <label for="wpgv-voucher-category-image-id"><?php _e( 'Featured Image', 'gift-voucher' ); ?></label>
       <input type="hidden" id="wpgv-voucher-category-image-id" name="wpgv-voucher-category-image-id" class="custom_media_url" value="">
       <div id="category-image-wrapper"></div>
       <p>
         <input type="button" class="button button-secondary wpgv_voucher_tax_media_button" id="wpgv_voucher_tax_media_button" name="wpgv_voucher_tax_media_button" value="<?php esc_html_e( 'Add Image', 'gift-voucher' ); ?>" />
         <input type="button" class="button button-secondary wpgv_voucher_tax_media_remove" id="wpgv_voucher_tax_media_remove" name="wpgv_voucher_tax_media_remove" value="<?php esc_html_e( 'Remove Image', 'gift-voucher' ); ?>" />
       </p>
     </div>
   <?php }

   /**
    * Save the form field
    * @since 1.0.0
    */
   public function save_category_image( $term_id, $tt_id ) {
     if( isset( $_POST['wpgv-voucher-category-image-id'] ) && '' !== $_POST['wpgv-voucher-category-image-id'] ){
       add_term_meta( $term_id, 'wpgv-voucher-category-image-id', absint( $_POST['wpgv-voucher-category-image-id'] ), true );
     }
    }

    /**
     * Edit the form field
     * @since 1.0.0
     */
    public function update_category_image( $term, $taxonomy ) { ?>
      <tr class="form-field term-group-wrap">
        <th scope="row">
          <label for="wpgv-voucher-category-image-id"><?php _e( 'Featured Image', 'gift-voucher' ); ?></label>
        </th>
        <td>
          <?php $image_id = get_term_meta( $term->term_id, 'wpgv-voucher-category-image-id', true ); ?>
          <input type="hidden" id="wpgv-voucher-category-image-id" name="wpgv-voucher-category-image-id" value="<?php echo esc_attr( $image_id ); ?>">
          <div id="category-image-wrapper">
            <?php if( $image_id ) { ?>
              <?php echo wp_get_attachment_image( $image_id, 'thumbnail' ); ?>
            <?php } ?>
          </div>
          <p>
            <input type="button" class="button button-secondary wpgv_voucher_tax_media_button" id="wpgv_voucher_tax_media_button" name="wpgv_voucher_tax_media_button" value="<?php esc_html_e( 'Add Image', 'gift-voucher' ); ?>" />
            <input type="button" class="button button-secondary wpgv_voucher_tax_media_remove" id="wpgv_voucher_tax_media_remove" name="wpgv_voucher_tax_media_remove" value="<?php esc_html_e( 'Remove Image', 'gift-voucher' ); ?>" />
          </p>
        </td>
      </tr>
   <?php }

   /**
    * Update the form field value
    * @since 1.0.0
    */
   public function updated_category_image( $term_id, $tt_id ) {
     if( isset( $_POST['wpgv-voucher-category-image-id'] ) && '' !== $_POST['wpgv-voucher-category-image-id'] ){
       update_term_meta( $term_id, 'wpgv-voucher-category-image-id', absint( $_POST['wpgv-voucher-category-image-id'] ) );
     } else {
       update_term_meta( $term_id, 'wpgv-voucher-category-image-id', '' );
     }
   }
 
   /**
    * Enqueue styles and scripts
    * @since 1.0.0
    */
   public function add_script() {
     if( ! isset( $_GET['taxonomy'] ) || $_GET['taxonomy'] != 'wpgv_voucher_category' ) {
       return;
     } ?>
     <script> jQuery(document).ready( function($) {
       _wpMediaViewsL10n.insertIntoPost = '<?php _e( "Insert", "gift-voucher" ); ?>';
       function ct_media_upload(button_class) {
         var _custom_media = true, _orig_send_attachment = wp.media.editor.send.attachment;
         $('body').on('click', button_class, function(e) {
           var button_id = '#'+$(this).attr('id');
           var send_attachment_bkp = wp.media.editor.send.attachment;
           var button = $(button_id);
           _custom_media = true;
           wp.media.editor.send.attachment = function(props, attachment){
             if( _custom_media ) {
               $('#wpgv-voucher-category-image-id').val(attachment.id);
               $('#category-image-wrapper').html('<img class="custom_media_image" src="" style="margin:0;padding:0;max-height:100px;float:none;" />');
               $( '#category-image-wrapper .custom_media_image' ).attr( 'src',attachment.url ).css( 'display','block' );
             } else {
               return _orig_send_attachment.apply( button_id, [props, attachment] );
             }
           }
           wp.media.editor.open(button); return false;
         });
       }
       ct_media_upload('.wpgv_voucher_tax_media_button.button');
       $('body').on('click','.wpgv_voucher_tax_media_remove',function(){
         $('#wpgv-voucher-category-image-id').val('');
         $('#category-image-wrapper').html('<img class="custom_media_image" src="" style="margin:0;padding:0;max-height:100px;float:none;" />');
       });
       // Thanks: http://stackoverflow.com/questions/15281995/wordpress-create-category-ajax-response
       $(document).ajaxComplete(function(event, xhr, settings) {
         var queryStringArr = settings.data.split('&');
         if( $.inArray('action=add-tag', queryStringArr) !== -1 ){
           var xml = xhr.responseXML;
           $response = $(xml).find('term_id').text();
           if($response!=""){
             // Clear the thumb image
             $('#category-image-wrapper').html('');
           }
          }
        });
      });
    </script>
   <?php }
  }
$WPGV_Voucher_Taxonomy_Image = new WPGV_Voucher_Taxonomy_Image();
$WPGV_Voucher_Taxonomy_Image->init(); }



// Add the voucher Meta Boxes
function wpgv_add_voucher_metaboxes() {
	add_meta_box('wpgv_voucher_amount', __('Item Details'), 'wpgv_voucher_amount', 'wpgv_voucher_product', 'normal', 'default');
}
add_action( 'add_meta_boxes', 'wpgv_add_voucher_metaboxes' );

function wpgv_add_edit_form_multipart_encoding() {
    echo ' enctype="multipart/form-data"';
}
add_action('post_edit_form_tag', 'wpgv_add_edit_form_multipart_encoding');

// The vouchers Metabox
function wpgv_voucher_amount() {
	global $post;

  if(function_exists( 'wp_enqueue_media' )){
    wp_enqueue_media();
  } 
  else
  {
    wp_enqueue_style('thickbox');
    wp_enqueue_script('media-upload');
    wp_enqueue_script('thickbox');
  }
	
	// Noncename needed to verify where the data originated
	echo '<input type="hidden" name="voucher_meta_noncename" id="voucher_meta_noncename" value="'.esc_html(wp_create_nonce(plugin_basename(__FILE__))).'" />';

	// Get the location data if its already been entered
  
  $description = esc_html(get_post_meta($post->ID, 'description', true));
  $price = esc_html(get_post_meta($post->ID, 'price', true));
  $special_price = esc_html(get_post_meta($post->ID, 'special_price', true));
  $style1_image = esc_html(get_post_meta($post->ID, 'style1_image', true));
  $style2_image = esc_html(get_post_meta($post->ID, 'style2_image', true));
  $style3_image = esc_html(get_post_meta($post->ID, 'style3_image', true));
	// Echo out the field
  echo '<p class="post-attributes-label-wrapper"><label class="post-attributes-label" for="description">'.__('Description').': (20 Words)</label></p><textarea name="description" id="description" class="widefat">' . esc_attr($description)  . '</textarea><div class="dt_hr dt_hr-bottom"></div>';
	echo '<p class="post-attributes-label-wrapper"><label class="post-attributes-label" for="price">'.__('Item Price').':</label></p><input type="number" name="price" id="price" class="widefat" value="' . esc_attr($price)  . '" step=".01"><div class="dt_hr dt_hr-bottom"></div>';
  echo '<p class="post-attributes-label-wrapper"><label class="post-attributes-label" for="special_price">'.__('Item Special Price').':</label></p><input type="number" name="special_price" id="special_price" class="widefat" value="' . esc_attr($special_price)  . '" step=".01"><div class="dt_hr dt_hr-bottom"></div>';

  for ($i=1; $i < 4; $i++) {
    if(${'style'.$i.'_image'}):
      $image_attributes = wp_get_attachment_image_src( ${'style'.$i.'_image'}, 'voucher-thumb' );
      ?>
      <script type="text/javascript">
        jQuery(document).ready(function($) {
          $('.image_src<?php echo esc_html($i); ?>').attr('src', '<?php echo esc_url($image_attributes[0]); ?>').show();
          $('.remove_image<?php echo esc_html($i); ?>').show();
        });
      </script>
      <?php
    endif;
  }

  $sizearr = array('', '1000px x 760px', '1000px x 1500px', '1000px x 750px');
  for ($i=1; $i < 4; $i++) {
    echo '<p class="post-attributes-label-wrapper"><label class="post-attributes-label" for="style'.esc_html($i).'_image">Image - Style '.esc_html($i).' (Recommended: '.esc_html($sizearr[$i]).'):</label></p>';
    ?>
    <img class="image_src<?php echo esc_html($i); ?>" src="" width="100" style="display: none;" />
    <input class="image_url<?php echo esc_html($i); ?>" type="hidden" name="style<?php echo esc_html($i); ?>_image" size="60" value="<?php echo esc_attr(${'style'.esc_html($i).'_image'}); ?>">
    <button type="button" class="upload_image<?php echo esc_html($i); ?> button"><?php echo __('Upload Image', 'gift-voucher' ) ?></button>
    <button type="button" class="button button-primary remove_image<?php echo esc_html($i); ?>" style="display: none;"><?php echo __('Remove Image', 'gift-voucher') ?></button><br>
  <?php } ?>
  <script>
      jQuery(document).ready(function($) {
        <?php for ($i=1; $i < 4; $i++) { ?>
          $('.upload_image<?php echo esc_html($i); ?>').click(function(e) {
              e.preventDefault();

              var custom_uploader = wp.media({
                  title: 'Add Voucher Image',
                  button: {
                      text: 'Upload Image'
                  },
                  multiple: false  // Set this to true to allow multiple files to be selected
              })
              .on('select', function() {
                  var attachment = custom_uploader.state().get('selection').first().toJSON();
                  $('.image_src<?php echo esc_html($i); ?>').attr('src', attachment.url).show();
                  $('.image_url<?php echo esc_html($i); ?>').val(attachment.id);
                  $('.remove_image<?php echo esc_html($i); ?>').show();
              })
              .open();
          });
          $('.remove_image<?php echo esc_html($i); ?>').click(function () {
            $('.image_src<?php echo esc_html($i); ?>').attr('src','').hide();
            $('.image_url<?php echo esc_html($i); ?>').val('');
              $('.remove_image<?php echo esc_html($i); ?>').hide();
          });
          <?php } ?>
      });
  </script>
  <?php
}

// Save the Metabox Data

function wpt_save_voucher_meta($post_id, $post) {
	// fix minh
	$voucher_meta_noncename = !empty(($_POST['voucher_meta_noncename'])) ? sanitize_textarea_field($_POST['voucher_meta_noncename']) : "";
	// verify this came from the our screen and with proper authorization,
	// because save_post can be triggered at other times
	if ( !wp_verify_nonce( 	($voucher_meta_noncename), plugin_basename(__FILE__) )) {
	return $post->ID;
	}

	// Is the user allowed to edit the post or page?
	if ( !current_user_can( 'edit_post', $post->ID ))
		return $post->ID;

	// OK, we're authenticated: we need to find and save the data
	// We'll put it into an array to make it easier to loop though.
  $events_meta['description'] = sanitize_textarea_field($_POST['description']);
  $events_meta['price'] = absint($_POST['price']);
  $events_meta['special_price'] = absint($_POST['special_price']);
  $events_meta['style1_image'] = sanitize_text_field($_POST['style1_image']);
  $events_meta['style2_image'] = sanitize_text_field($_POST['style2_image']);
  $events_meta['style3_image'] = sanitize_text_field($_POST['style3_image']);
	
	// Add values of $events_meta as custom fields
	foreach ($events_meta as $key => $value) { // Cycle through the $events_meta array!
		if( $post->post_type == 'revision' ) return; // Don't store custom data twice
		$value = implode(',', (array)$value); // If $value is an array, make it a CSV (unlikely)
    $post_id = (int) sanitize_text_field( $post->ID );
		if(esc_html(get_post_meta($post_id, $key, FALSE))) { // If the custom field already has a value
      
			update_post_meta($post_id, $key, $value);
		} else { // If the custom field doesn't have a value
      
			add_post_meta($post_id, $key, $value);
		}
		if(!$value) delete_post_meta($post_id, $key); // Delete if blank
	}

}

add_action('save_post', 'wpt_save_voucher_meta', 1, 2); // save the voucher meta fields
class Template_Voucher { 
  private $screens = array(
      'voucher_template',
  );
  private $fields = array(
      array(
          'id' => 'template-style',
          'label' => 'Template Style Lanscape(Right click to open the image)',
          'type' => 'radio',
          'options' => array(
              'template-voucher-lanscape-4.png',
              'template-voucher-lanscape-8.png',
              'template-voucher-lanscape-10.png',
              'template-voucher-portail-1.png',
              'template-voucher-portail-2.png',
              'template-voucher-portail-6.png',
          ),
      ),
      array(
          'id' => 'status',
          'label' => 'Status',
          'type' => 'select',
          'options' => array(
              'Active',
              'Inactive',
          ),
      ),
      array(
          'id' => 'voucher_expiry_value',
          'label' => 'Voucher Expiry Value',
          'type' => 'date_expiry',
      ),
  );
  public function __construct() {
      add_action( 'add_meta_boxes', array($this,'add_meta_boxes') );
      add_action( 'admin_footer', array( $this, 'admin_footer' ) );
      add_action( 'save_post', array( $this, 'save_post' ) );
  }
  // Add the voucher Meta Boxes
  public function add_meta_boxes() {
      foreach ( $this->screens as $screen ) {
          add_meta_box(
              'customize-template',
              __( 'Customize Template', 'gift-voucher' ),
              array( $this, 'add_meta_box_callback' ),
              $screen,
              'normal',
              'high'
          );
      }
  }
  /**
   * Generates the HTML for the meta box
   * 
   * @param object $post WordPress post object
   */
  public function add_meta_box_callback( $post ) {
      wp_nonce_field( 'wpgv_customize_template_data', 'wpgv_customize_template_nonce' );
      $this->generate_fields( $post );
      $post_id_sanitize = (int) sanitize_text_field( $post->ID );
      // if(get_post_meta( $post_id_sanitize, 'wpgv_customize_template_template-style', true )) {
      //     echo '<a href="#" class="button">See Sample Preview</a>';           
      // }
  }
  /**
   * Hooks into WordPress' admin_footer function.
   * Adds scripts for media uploader.
   */
  public function admin_footer() {
      ?><script>
          // https://codestag.com/how-to-use-wordpress-3-5-media-uploader-in-theme-options/
          jQuery(document).ready(function($){
              if ( typeof wp.media !== 'undefined' ) {
                  var _custom_media = true,
                  _orig_send_attachment = wp.media.editor.send.attachment;
                  $('.rational-metabox-media').click(function(e) {
                      var send_attachment_bkp = wp.media.editor.send.attachment;
                      var button = $(this);
                      var id = button.attr('id').replace('_button', '');
                      _custom_media = true;
                          wp.media.editor.send.attachment = function(props, attachment){
                          if ( _custom_media ) {
                              $("#"+id).val(attachment.url);
                          } else {
                              return _orig_send_attachment.apply( this, [props, attachment] );
                          };
                      }
                      wp.media.editor.open(button);
                      return false;
                  });
                  $('.add_media').on('click', function(){
                      _custom_media = false;
                  });
              }  
          });
      </script><?php
  }
  /**
   * Generates the field's HTML for the meta box.
   */
  public function generate_fields( $post ) {
      $output = '';
      foreach ( $this->fields as $field ) {
          $label = '<label for="' . $field['id'] . '">' . $field['label'] . '</label>';
          $post_id_sanitize = (int) sanitize_text_field( $post->ID );
          $field_id_sanitize = sanitize_text_field( $field['id'] );
          $db_value = esc_html(get_post_meta( $post_id_sanitize, 'wpgv_customize_template_' . $field_id_sanitize, true ));
          switch ( $field['type'] ) {
             
              case 'radio':
                $input = '<fieldset>';
                $input .= '<legend class="screen-reader-text">' . esc_html($field['label']) . '</legend>';
                $i = 0;
                foreach ( $field['options'] as $key => $value ) {
                    $field_value = !is_numeric( $key ) ? esc_attr($key) : esc_attr($value);
                    $input .= sprintf(
                        '<label><input %s id="%s" name="%s" type="radio" value="%s"><img src="%s"></label>%s',
                        $db_value === $field_value ? 'checked' : '',
                        sanitize_text_field(esc_attr($field['id'])),
                        sanitize_text_field(esc_attr($field['id'])),
                        sanitize_text_field($field_value),
                        sanitize_url(WPGIFT__PLUGIN_URL.'/assets/img/templates/png/'.$value),
                        $i < count( $field['options'] ) - 1 ? '' : ''
                    );
                    $i++;
                }
                $input .= '</fieldset>';
                break;
              case 'select':
                  $input = sprintf(
                      '<select class="min-fix" id="%s" name="%s" class="regular-text">',
                      sanitize_text_field(esc_attr($field['id'])),
                      sanitize_text_field(esc_attr($field['id']))
                  );
                  foreach ( $field['options'] as $key => $value ) {
                      $field_value = !is_numeric( $key ) ? esc_attr($key) : esc_attr($value);
                      $input .= sprintf(
                          '<option %s value="%s">%s</option>',
                          sanitize_text_field($db_value) === sanitize_text_field($field_value) ? sanitize_text_field(esc_html('selected')) : '',
                          sanitize_text_field($field_value),
                          sanitize_text_field(esc_attr($value))
                      );
                  }
                  $input .= '</select>';
                  break;
             
              case 'date_expiry':
                  $input = sprintf(
                      '<input %s id="%s" name="%s" type="%s" value="%s" min="1">',
                      sanitize_text_field(esc_attr($field['type'])) !== esc_html('color') ? esc_html('class="regular-text"') : '',
                      sanitize_text_field(esc_attr($field['id'])),
                      sanitize_text_field(esc_attr($field['id'])),
                      sanitize_text_field(esc_attr($field['type'])) == sanitize_text_field(esc_html('date_expiry')) ? sanitize_text_field(esc_html('number')) : '',
                      sanitize_text_field(esc_attr($db_value)) == Null ? sanitize_text_field(esc_html('60')) : sanitize_text_field(esc_attr($db_value))
                  );
                  break;
          }
          $output .= $this->row_format( $label, $input );
      }
      $arr = array( 
        'fieldset' => array(), 
        'legend' => array(
          'class' => array(),
        ), 
        'label' => array(
          'for' => array(),
        ),
        'input' => array(
          'class' => array(),
          'checked' => array(),
          'id'   => array(),
          'name'   => array(),
          'type'   => array(),
          'value'   => array(),
          'min'   => array(),
        ),
        'img' => array(
          'title' => array(),
          'src'   => array(),
          'alt'   => array(),
        ),
        'select' => array(
          'class' => array(),
          'id' => array(),
          'name' => array(),
        ),
        'option' => array(
          'selected' => array(),
          'value' => array(),
        ),
        'tr' => array(),
        'th' => array(
          'scope' => array(),
        ),
        'td' => array(),
        
      );
      echo '<table class="form-table wpgv-template-box"><tbody>' . wp_kses( $output, $arr ) . '</tbody></table>';
  }
  /**
   * Generates the HTML for table rows.
   */
  public function row_format( $label, $input ) {
      return sprintf(
          '<tr><th scope="row">%s</th><td>%s</td></tr>',
          $label,
          $input
      );
  }
  /**
   * Hooks into WordPress' save_post function
   */
  public function save_post( $post_id ) {
      if ( ! isset( $_POST['wpgv_customize_template_nonce'] ) )
          return $post_id;
      $nonce = $_POST['wpgv_customize_template_nonce'];
      if ( !wp_verify_nonce( $nonce, 'wpgv_customize_template_data' ) )
          return $post_id;
      if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
          return $post_id;
      foreach ( $this->fields as $field ) {
          if ( isset( $_POST[ $field['id'] ] ) ) {                
              switch ( $field['type'] ) {
                  case 'email':
                      $_POST[ $field['id'] ] = (int) sanitize_email( $_POST[ $field['id'] ] );
                      break;
                  case 'text':
                      $_POST[ $field['id'] ] = (int) sanitize_text_field( $_POST[ $field['id'] ] );
                  case 'number':
                      $_POST[ $field['id'] ] = (int) sanitize_text_field( $_POST[ $field['id'] ] );
                      break;
              }
              $post_id = (int) sanitize_text_field( $post_id );
              update_post_meta( $post_id, 'wpgv_customize_template_' . sanitize_text_field($field['id']), sanitize_text_field($_POST[ $field['id'] ]) );
          } else if ( $field['type'] === 'checkbox' ) {
              $post_id = (int) sanitize_text_field( $post_id );
              update_post_meta( $post_id, 'wpgv_customize_template_' . sanitize_text_field($field['id']), '0' );
          }
      }
  } 
}
new Template_Voucher;