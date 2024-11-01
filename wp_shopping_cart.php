<?php
/*
Plugin Name: WP Simple Zarinpal Shopping cart
Version: v1.0.1
Author: Mostafa Amiri
Author URI: http://www.samansystems.com/

Based on Work of:
Core Author: Ruhul Amin
Core URI: http://www.tipsandtricks-hq.com/
Description: Simple WordPress Shopping Cart Plugin, very easy to use and great for selling products and services from your blog, Integrated with Zarinpal Merchant!
*/

/*
    This program is free software; you can redistribute it
    under the terms of the GNU General Public License version 2,
    as published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
*/
if(!isset($_SESSION)) 
{
	session_start();
}	

$siteurl = get_option('siteurl');
define('WP_CART_FOLDER', dirname(plugin_basename(__FILE__)));
define('WP_CART_URL', get_option('siteurl').'/wp-content/plugins/' . WP_CART_FOLDER);

add_option('wp_cart_title', 'سبد خريد شما');
add_option('wp_cart_empty_text', 'سبد خريد شما خالی می باشد');
add_option('cart_return_from_zarinpal_url', get_bloginfo('wpurl'));

function always_show_cart_handler($atts) 
{
	return print_wp_shopping_cart();
}

function show_wp_shopping_cart_handler()
{
    if (cart_not_empty())
    {
       	$output = print_wp_shopping_cart();
    }
    return $output;	
}

function shopping_cart_show($content)
{
	if (strpos($content, "<!--show-wp-shopping-cart-->") !== FALSE)
    {
    	if (cart_not_empty())
    	{
        	$content = preg_replace('/<p>\s*<!--(.*)-->\s*<\/p>/i', "<!--$1-->", $content);
        	$matchingText = '<!--show-wp-shopping-cart-->';
        	$replacementText = print_wp_shopping_cart();
        	$content = str_replace($matchingText, $replacementText, $content);
    	}
    }
    return $content;
}

// Reset the Cart as this is a returned customer from Paypal
$merchant_return_link = $_GET["merchant_return_link"];
if (!empty($merchant_return_link))
{
    reset_wp_cart();
}
$mc_gross = $_GET["mc_gross"];
if ($mc_gross > 0)
{
    reset_wp_cart();
}

function reset_wp_cart()
{
    $products = $_SESSION['simpleCart'];
    foreach ($products as $key => $item)
    {
        unset($products[$key]);
    }
    $_SESSION['simpleCart'] = $products;
    header('Location: ' . get_option('cart_return_from_zarinpal_url'));
}

if ($_POST['addcart'])
{
    $count = 1;    
    $products = $_SESSION['simpleCart'];
    
    if (is_array($products))
    {
        foreach ($products as $key => $item)
        {
            if ($item['name'] == stripslashes($_POST['product']))
            {
                $count += $item['quantity'];
                $item['quantity']++;
                unset($products[$key]);
                array_push($products, $item);
            }
        }
    }
    else
    {
        $products = array();
    }
        
    if ($count == 1)
    {
        if (!empty($_POST[$_POST['product']]))
            $price = $_POST[$_POST['product']];
        else
            $price = $_POST['price'];
        
        $product = array('name' => stripslashes($_POST['product']), 'price' => $price, 'quantity' => $count, 'shipping' => $_POST['shipping'], 'cartLink' => $_POST['cartLink'], 'item_number' => $_POST['item_number']);
        array_push($products, $product);
    }
    
    sort($products);
    $_SESSION['simpleCart'] = $products;
}
else if ($_POST['cquantity'])
{
    $products = $_SESSION['simpleCart'];
    foreach ($products as $key => $item)
    {
        if ((stripslashes($item['name']) == stripslashes($_POST['product'])) && $_POST['quantity'])
        {
            $item['quantity'] = $_POST['quantity'];
            unset($products[$key]);
            array_push($products, $item);
        }
        else if (($item['name'] == stripslashes($_POST['product'])) && !$_POST['quantity'])
            unset($products[$key]);
    }
    sort($products);
    $_SESSION['simpleCart'] = $products;
}
else if ($_POST['delcart'])
{
    $products = $_SESSION['simpleCart'];
    foreach ($products as $key => $item)
    {
        if ($item['name'] == stripslashes($_POST['product']))
            unset($products[$key]);
    }
    $_SESSION['simpleCart'] = $products;
}

function print_wp_shopping_cart()
{
	if (!cart_not_empty())
	{
	    $empty_cart_text = get_option('wp_cart_empty_text');
		if (!empty($empty_cart_text)) 
		{
			$output .= $empty_cart_text;
		}
		$cart_products_page_url = get_option('cart_products_page_url');
		if (!empty($cart_products_page_url))
		{
			$output .= '<br /><a rel="nofollow" href="'.$cart_products_page_url.'">مشاهده ساير محصولات</a>';
		}		
		return $output;
	}

    $use_affiliate_platform = get_option('wp_use_aff_platform');   
    $zarinpal_id = get_option('cart_zarinpal_id');

	$toman_symbol = ' تومان';
     
    $decimal = '.';  
	$urls = '';
		
	$title = get_option('wp_cart_title');
	//if (empty($title)) $title = 'Your Shopping Cart';
    
    global $plugin_dir_name;
    $output .= '<div class="shopping_cart" style=" padding: 5px;">';
    if (!get_option('wp_shopping_cart_image_hide'))    
    {
    	$output .= "<input type='image' src='".WP_CART_URL."/images/shopping_cart_icon.png' value='Cart' title='Cart' />";
    }
    if(!empty($title))
    {
    	$output .= '<h2>';
    	$output .= $title;  
    	$output .= '</h2>';
    }
        
    $output .= '<br /><span id="pinfo" style="display: none; font-weight: bold; color: red;">جهت ثبت تعداد Enter را فشار دهيد.</span>';
	$output .= '<table style="width: 100%;">';    
    
    $count = 1;
    $total_items = 0;
    $total = 0;
    $form = '';
    if ($_SESSION['simpleCart'] && is_array($_SESSION['simpleCart']))
    {   
        $output .= '
        <tr>
        <th style="text-align: right">نام محصول</th><th>تعداد</th><th>قيمت</th>
        </tr>';
    
	    foreach ($_SESSION['simpleCart'] as $item)
	    {
	        $total += $item['price'] * $item['quantity'];
	        $item_total_shipping += $item['shipping'] * $item['quantity'];
	        $total_items +=  $item['quantity'];
	    }
	    $baseShipping = get_option('cart_base_shipping_cost');
	    $postage_cost = $item_total_shipping + $baseShipping;
	    
	    $cart_free_shipping_threshold = get_option('cart_free_shipping_threshold');
	    if (!empty($cart_free_shipping_threshold) && $total > $cart_free_shipping_threshold)
	    {
	    	$postage_cost = 0;
	    }

	    foreach ($_SESSION['simpleCart'] as $item)
	    {
	        $output .= "
	        <tr><td style='overflow: hidden;'><a href='".$item['cartLink']."'>".$item['name']."</a></td>
	        <td style='text-align: center'><form method=\"post\"  action=\"\" name='pcquantity' style='display: inline'>
                <input type=\"hidden\" name=\"product\" value=\"".$item['name']."\" />

	        <input type='hidden' name='cquantity' value='1' /><input type='text' name='quantity' value='".$item['quantity']."' size='1' onchange='document.pcquantity.submit();' onkeypress='document.getElementById(\"pinfo\").style.display = \"\";' /></form></td>
	        <td style='text-align: center'>".print_payment_currency(($item['price'] * $item['quantity']), $toman_symbol, $decimal)."</td>
	        <td><form method=\"post\"  action=\"\">
	        <input type=\"hidden\" name=\"product\" value=\"".$item['name']."\" />
	        <input type='hidden' name='delcart' value='1' />
	        <input type='image' src='".WP_CART_URL."/images/Shoppingcart_delete.png' value='Remove' title='Remove' /></form></td></tr>
	        ";
			
			$desc  .= 'محصول: '.$item['name'].' تعداد:'.$item['quantity'];
	        $count++;
	    }   	    
    }
    
       	$count--;
       	
       	if ($count)
       	{
       		//$output .= '<tr><td></td><td></td><td></td></tr>';  

            if ($postage_cost != 0)
            {
                $output .= "
                <tr><td colspan='2' style='font-weight: bold; text-align: right;'>مجموع: </td><td style='text-align: center'>".print_payment_currency($total, $toman_symbol, $decimal)."</td><td></td></tr>
                <tr><td colspan='2' style='font-weight: bold; text-align: right;'>هزينه ارسال: </td><td style='text-align: center'>".print_payment_currency($postage_cost, $toman_symbol, $decimal)."</td><td></td></tr>";
            }

            $output .= "
       		<tr><td colspan='2' style='font-weight: bold; text-align: right;'>مجموع کل: </td><td style='text-align: center'>".print_payment_currency(($total+$postage_cost), $toman_symbol, $decimal)."</td><td></td></tr>
       		<tr><td colspan='4'>";
			
			$return = get_option('cart_return_from_zarinpal_url');
       
              	$output .= "<form action=\"https://www.zarinpal.com/webservice/Simplepay\" method=\"post\" id=\"TransactionAddForm\">";
    			if ($count)
            		$output .= '<br /><center><input type="image" src="http://www.zarinpal.com/img/merchant/merchant-6.png"></center>';
       
    			$output .= $urls.'
			    <input type="hidden" id="TransactionAccountID" value="'.$zarinpal_id.'" name="data[Transaction][account_id]">
				<input type="hidden" id="TransactionAmount" value="'.($total+$postage_cost).'" name="data[Transaction][amount]">
				<input type="hidden" id="TransactionDesc" value="'.$desc.'" name="data[Transaction][desc]">
				<input type="hidden" id="TransactionRedirectUrl" value="'.$return.'" name="data[Transaction][redirect_url]">'.
				'</form>';          
       	}       
       	$output .= "       
       	</td></tr>
    	</table></div>
    	";
    
    return $output;
}
// https://www.sandbox.paypal.com/cgi-bin/webscr (paypal testing site)
// https://www.paypal.com/us/cgi-bin/webscr (paypal live site )

function print_wp_cart_button_new($content)
{
	//wp_cart_add_read_form_javascript();
        
        $addcart = get_option('addToCartButtonName');    
        if (!$addcart || ($addcart == '') )
            $addcart = 'Add to Cart';
            	
        $pattern = '#\[wp_cart:.+:price:.+:end]#';
        preg_match_all ($pattern, $content, $matches);

        foreach ($matches[0] as $match)
        {   
        	$var_output = '';
            $pos = strpos($match,":var1");
			if ($pos)
			{				
				$match_tmp = $match;
				// Variation control is used
				$pos2 = strpos($match,":var2");
				if ($pos2)
				{
					//echo '<br />'.$match_tmp.'<br />';
					$pattern = '#var2\[.*]:#';
				    preg_match_all ($pattern, $match_tmp, $matches3);
				    $match3 = $matches3[0][0];
				    //echo '<br />'.$match3.'<br />';
				    $match_tmp = str_replace ($match3, '', $match_tmp);
				    
				    $pattern = 'var2[';
				    $m3 = str_replace ($pattern, '', $match3);
				    $pattern = ']:';
				    $m3 = str_replace ($pattern, '', $m3);  
				    $pieces3 = explode('|',$m3);
			
				    $variation2_name = $pieces3[0];
				    $var_output .= $variation2_name." : ";
				    $var_output .= '<select name="variation2" onchange="ReadForm (this.form, false);">';
				    for ($i=1;$i<sizeof($pieces3); $i++)
				    {
				    	$var_output .= '<option value="'.$pieces3[$i].'">'.$pieces3[$i].'</option>';
				    }
				    $var_output .= '</select><br />';				    
				}				
			    
			    $pattern = '#var1\[.*]:#';
			    preg_match_all ($pattern, $match_tmp, $matches2);
			    $match2 = $matches2[0][0];

			    $match_tmp = str_replace ($match2, '', $match_tmp);

				    $pattern = 'var1[';
				    $m2 = str_replace ($pattern, '', $match2);
				    $pattern = ']:';
				    $m2 = str_replace ($pattern, '', $m2);  
				    $pieces2 = explode('|',$m2);
			
				    $variation_name = $pieces2[0];
				    $var_output .= $variation_name." : ";
				    $var_output .= '<select name="variation1" onchange="ReadForm (this.form, false);">';
				    for ($i=1;$i<sizeof($pieces2); $i++)
				    {
				    	$var_output .= '<option value="'.$pieces2[$i].'">'.$pieces2[$i].'</option>';
				    }
				    $var_output .= '</select><br />';				

			}

            $pattern = '[wp_cart:';
            $m = str_replace ($pattern, '', $match);
            
            $pattern = 'price:';
            $m = str_replace ($pattern, '', $m);
            $pattern = 'shipping:';
            $m = str_replace ($pattern, '', $m);
            $pattern = ':end]';
            $m = str_replace ($pattern, '', $m);

            $pieces = explode(':',$m);
    
                $replacement = '<object><form method="post"  action="" style="display:inline" onsubmit="return ReadForm(this, true);">';             
                if (!empty($var_output))
                {
                	$replacement .= $var_output;
                } 
				                
				if (preg_match("/http/", $addcart)) // Use the image as the 'add to cart' button
				{
				    $replacement .= '<input type="image" src="'.$addcart.'" class="wp_cart_button" alt="Add to Cart"/>';
				} 
				else 
				{
				    $replacement .= '<input type="submit" value="'.$addcart.'" />';
				} 

                $replacement .= '<input type="hidden" name="product" value="'.$pieces['0'].'" /><input type="hidden" name="price" value="'.$pieces['1'].'" />';
                $replacement .= '<input type="hidden" name="product_tmp" value="'.$pieces['0'].'" />';
                if (sizeof($pieces) >2 )
                {
                	//we have shipping
                	$replacement .= '<input type="hidden" name="shipping" value="'.$pieces['2'].'" />';
                }
                $replacement .= '<input type="hidden" name="cartLink" value="'.cart_current_page_url().'" />';
                $replacement .= '<input type="hidden" name="addcart" value="1" /></form></object>';
                $content = str_replace ($match, $replacement, $content);                
        }
        return $content;	
}

function wp_cart_add_read_form_javascript()
{
	echo '
	<script type="text/javascript">
	<!--
	//
	function ReadForm (obj1, tst) 
	{ 
	    // Read the user form
	    var i,j,pos;
	    val_total="";val_combo="";		
	
	    for (i=0; i<obj1.length; i++) 
	    {     
	        // run entire form
	        obj = obj1.elements[i];           // a form element
	
	        if (obj.type == "select-one") 
	        {   // just selects
	            if (obj.name == "quantity" ||
	                obj.name == "amount") continue;
		        pos = obj.selectedIndex;        // which option selected
		        val = obj.options[pos].value;   // selected value
		        val_combo = val_combo + "(" + val + ")";
	        }
	    }
		// Now summarize everything we have processed above
		val_total = obj1.product_tmp.value + val_combo;
		obj1.product.value = val_total;
	}
	//-->
	</script>';	
}
function print_wp_cart_button_for_product($name, $price, $shipping=0)
{
        $addcart = get_option('addToCartButtonName');
    
        if (!$addcart || ($addcart == '') )
            $addcart = 'افزودن به سبدخريد';
                  

        $replacement = '<object><form method="post"  action="" style="display:inline">';
		if (preg_match("/http:/", $addcart)) // Use the image as the 'add to cart' button
		{
			$replacement .= '<input type="image" src="'.$addcart.'" class="wp_cart_button" alt="افزودن به سبدخريد"/>';
		} 
		else 
		{
		    $replacement .= '<input type="submit" value="'.$addcart.'" />';
		}             	      

        $replacement .= '<input type="hidden" name="product" value="'.$name.'" /><input type="hidden" name="price" value="'.$price.'" /><input type="hidden" name="shipping" value="'.$shipping.'" /><input type="hidden" name="addcart" value="1" /><input type="hidden" name="cartLink" value="'.cart_current_page_url().'" /></form></object>';
                
        return $replacement;
}

function cart_not_empty()
{
        $count = 0;
        if (isset($_SESSION['simpleCart']) && is_array($_SESSION['simpleCart']))
        {
            foreach ($_SESSION['simpleCart'] as $item)
                $count++;
            return $count;
        }
        else
            return 0;
}

function print_payment_currency($price, $symbol, $decimal)
{
    return number_format($price, 0, $decimal, ',').$symbol;
}

function cart_current_page_url() {
 $pageURL = 'http';
 if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
 $pageURL .= "://";
 if ($_SERVER["SERVER_PORT"] != "80") {
  $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
 } else {
  $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
 }
 return $pageURL;
}

function show_wp_cart_options_page () {	
	$wp_simple_zarinpal_shopping_cart_version = "1.0.1";
    if (isset($_POST['info_update']))
    {
        update_option('cart_payment_currency', (string)$_POST["cart_payment_currency"]);
        update_option('cart_currency_symbol', (string)$_POST["cart_currency_symbol"]);
        update_option('cart_base_shipping_cost', (string)$_POST["cart_base_shipping_cost"]);
        update_option('cart_free_shipping_threshold', (string)$_POST["cart_free_shipping_threshold"]);   
        update_option('wp_shopping_cart_collect_address', ($_POST['wp_shopping_cart_collect_address']!='') ? 'checked="checked"':'' );    
        update_option('wp_shopping_cart_use_profile_shipping', ($_POST['wp_shopping_cart_use_profile_shipping']!='') ? 'checked="checked"':'' );
                
        update_option('cart_zarinpal_id', (string)$_POST["cart_zarinpal_id"]);
        update_option('addToCartButtonName', (string)$_POST["addToCartButtonName"]);
        update_option('wp_cart_title', (string)$_POST["wp_cart_title"]);
        update_option('wp_cart_empty_text', (string)$_POST["wp_cart_empty_text"]);
        update_option('cart_return_from_zarinpal_url', (string)$_POST["cart_return_from_zarinpal_url"]);
        update_option('cart_products_page_url', (string)$_POST["cart_products_page_url"]);
        update_option('wp_shopping_cart_image_hide', ($_POST['wp_shopping_cart_image_hide']!='') ? 'checked="checked"':'' );
        update_option('wp_use_aff_platform', ($_POST['wp_use_aff_platform']!='') ? 'checked="checked"':'' );
        
        echo '<div id="message" class="updated fade">';
        echo '<p><strong>تنظيمات به روز رسانی شد!</strong></p></div>';
    }	
	
    $defaultCurrency = get_option('cart_payment_currency');    
    if (empty($defaultCurrency)) $defaultSymbol = $defaultCurrency = 'تومان';

    $baseShipping = get_option('cart_base_shipping_cost');
    if (empty($baseShipping)) $baseShipping = 0;
    
    $cart_free_shipping_threshold = get_option('cart_free_shipping_threshold');

    $zarinpal_id = get_option('cart_zarinpal_id');
    
    $return_url =  get_option('cart_return_from_zarinpal_url');

    $addcart = get_option('addToCartButtonName');
    if (empty($addcart)) $addcart = 'Add to Cart';           

	$title = get_option('wp_cart_title');
	//if (empty($title)) $title = 'Your Shopping Cart';
	
	$emptyCartText = get_option('wp_cart_empty_text');
	$cart_products_page_url = get_option('cart_products_page_url');	      
        	    
    if (get_option('wp_shopping_cart_collect_address'))
        $wp_shopping_cart_collect_address = 'checked="checked"';
    else
        $wp_shopping_cart_collect_address = '';
        
    if (get_option('wp_shopping_cart_use_profile_shipping'))
        $wp_shopping_cart_use_profile_shipping = 'checked="checked"';
    else
        $wp_shopping_cart_use_profile_shipping = '';
                	
    if (get_option('wp_shopping_cart_image_hide'))
        $wp_cart_image_hide = 'checked="checked"';
    else
        $wp_cart_image_hide = '';

    if (get_option('wp_use_aff_platform'))
        $wp_use_aff_platform = 'checked="checked"';
    else
        $wp_use_aff_platform = '';
                              
	?>
 	<h2>ماژول آسان پرداز و سبدخريد زرين پال نسخه <?php echo $wp_simple_zarinpal_shopping_cart_version; ?></h2>
 	
 	<p>اين ماژول به شما امکان دريافت وجه آنلاين مربوط به سفارش را از طريق درگاه زرين پال و به کمک سرويس "آسان پرداز" زرين پال ميدهد. توجه کنيد که با توجه به اينکه اين سرويس از آسان پرداز زرين پال استفاده ميکند مشتری بايد پس از تکميل عمليات پرداخت، حتما شماره تراکنش را به شما اطلاع دهد و يا از طريق بخش تماس با ما شماره تراکنش را برای مديريت سايت ارسال کند. پس توجه داشته باشيد که حتما از مشتری خود درخواست نمائيد تا شماره تراکنش را در پايان تراکنش ياداشت نموده و به شما اطلاع دهد. استفاده از آسان پرداز زرين پال معايب و مزايايي دارد که از جمله مزايای آن ميتوان به عدم وابستگی ماژول به هاست و آی پی سرور و همچنين عدم نياز به درخواست درگاه پرداخت از زرين پال ميباشد. بدين صورت که کاربر برای استفاده از اين ماژول، نيازی به درخواست درگاه پرداخت از زرين پال نخواهد داشت.<br />
	</p>
    
     <fieldset class="options">
    <legend>نحوه استفاده:</legend>

    <p>1. برای افزودن لينک افزودن به سبد خريد از تگ <strong>[wp_cart:PRODUCT-NAME:price:PRODUCT-PRICE:end]</strong> در صفحات و يا نوشته های خود استفاده نماييد. PRODUCT-NAME با نام محصول و PRODUCT-PRICE با قيمت آن پر نماييد. For example: [wp_cart:ساعت مچی:price:15000:end]</p>
	<p>2. برای افزودن سبد خريد کافی است تگ <strong>[show_wp_shopping_cart]</strong> در صفحات و يا نوشته ها و يا ويجت های سايت قرار دهيد.</p> 
    </fieldset>

    <form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
    <input type="hidden" name="info_update" id="info_update" value="true" />    
 	<?php
echo '
	<div class="postbox">
	<h3><label for="title">تنظيمات زرين پال و سبد خريد</label></h3>
	<div class="inside">';

echo '
<table class="form-table">
<tr valign="top">
<th scope="row">ZP.id شما</th>
<td><input type="text" name="cart_zarinpal_id" value="'.$zarinpal_id.'" size="40" /></td>
</tr>
<tr valign="top">
<th scope="row">عنوان سبد خريد</th>
<td><input type="text" name="wp_cart_title" value="'.$title.'" size="40" /></td>
</tr>
<tr valign="top">
<th scope="row">نوشته مورد نظر در صورت خالی بودن سبد خريد</th>
<td><input type="text" name="wp_cart_empty_text" value="'.$emptyCartText.'" size="40" /></td>
</tr>
<tr valign="top">
<th scope="row">واحد پول</th>
<td><input type="text" name="cart_payment_currency" value="'.$defaultCurrency.'" size="6" /> (تومان)</td>
</tr>

<tr valign="top">
<th scope="row">هزينه ارسال پايه</th>
<td><input type="text" name="cart_base_shipping_cost" value="'.$baseShipping.'" size="5" /> <br />0 به معنای عدم هزينه برای ارسال می باشد. </td>
</tr>

<tr valign="top">
<th scope="row">هزينه ارسال رايگان برای سفارشات بيش از</th>
<td><input type="text" name="cart_free_shipping_threshold" value="'.$cart_free_shipping_threshold.'" size="5" /> <br />در صورت سفارش بيش از اين رقم هزينه ارسال رايگان می شود. برای عدم استفاده اين فيلد را خالی رها کنيد.</td>
</tr>
		
<tr valign="top">
<th scope="row">عکس و يا نوشته افزودن به سبد خريد</th>
<td><input type="text" name="addToCartButtonName" value="'.$addcart.'" size="100" /><br />برای استفاده از عکس URL عکس را وارد نماييد.</td>
</tr>

<tr valign="top">
<th scope="row">آدرس صفحه بازگشت از خريد</th>
<td><input type="text" name="cart_return_from_zarinpal_url" value="'.$return_url.'" size="100" /><br />پس از طی مراحل خريد کاربر به اين صفحه باز می گردد</td>
</tr>
		
<tr valign="top">
<th scope="row">آدرس صفحه محصولات</th>
<td><input type="text" name="cart_products_page_url" value="'.$cart_products_page_url.'" size="100" /><br />لينک صفحه ليست محصولات.  برای عدم استفاده اين فيلد را خالی رها کنيد.</td>
</tr>
</table>


<table class="form-table">
<tr valign="top">
<th scope="row">پنهان سازی عکس افزودن به سبد</th>
<td><input type="checkbox" name="wp_shopping_cart_image_hide" value="1" '.$wp_cart_image_hide.' /><br /></td>
</tr>
</table>

<table class="form-table">
<tr valign="top">
<th scope="row">استفاده از نسخه بازاريابی WP Affiliate</th>
<td><input type="checkbox" name="wp_use_aff_platform" value="1" '.$wp_use_aff_platform.' /><br />برای اطلاعات بيشتر به  <a href="http://tipsandtricks-hq.com/?p=1474" target="_blank">WP Affiliate Platform plugin</a> رجوع کنيد.</td>
</tr>
</table>
</div></div>
    <div class="submit">
        <input type="submit" name="info_update" value="به روز رسانی &raquo;" />
    </div>						
 </form>
 ';
    echo 'از اين ماژول خوشت اوومده ؟! <a href="http://wordpress.org/extend/plugins/zarinpal-simple-shopping-cart/ target="_blank">يه نمره مشتی بهش بده پس!</a>'; 
}

function wp_cart_options()
{
     echo '<div class="wrap"><h2>تنظيمات ماژول آسان پرداز زرين پال</h2>';
     echo '<div id="poststuff"><div id="post-body">';
     show_wp_cart_options_page();
     echo '</div></div>';
     echo '</div>';
}

// Display The Options Page
function wp_cart_options_page () 
{
     add_options_page('سبد خريد آسان پرداز زرين پال', 'آسان پرداز زرين پال', 'manage_options', __FILE__, 'wp_cart_options');  
}

function show_wp_paypal_shopping_cart_widget($args)
{
	extract($args);
	
	$cart_title = get_option('wp_cart_title');
	if (empty($cart_title)) $cart_title = 'Shopping Cart';
	
	echo $before_widget;
	echo $before_title . $cart_title . $after_title;
    echo print_wp_shopping_cart();
    echo $after_widget;
}

function wp_paypal_shopping_cart_widget_control()
{
    ?>
    <p>
    <? _e("Set the Plugin Settings from the Settings menu"); ?>
    </p>
    <?php
}

function widget_wp_paypal_shopping_cart_init()
{	
    $widget_options = array('classname' => 'widget_wp_paypal_shopping_cart', 'description' => __( "Display WP Paypal Shopping Cart.") );
    wp_register_sidebar_widget('wp_paypal_shopping_cart_widgets', __('آسان پرداز زرين پال'), 'show_wp_paypal_shopping_cart_widget', $widget_options);
    wp_register_widget_control('wp_paypal_shopping_cart_widgets', __('آسان پرداز زرين پال'), 'wp_paypal_shopping_cart_widget_control' );
}

function wp_cart_css()
{
    echo '<link type="text/css" rel="stylesheet" href="'.WP_CART_URL.'/wp_shopping_cart_style.css" />'."\n";
}

// Insert the options page to the admin menu
add_action('admin_menu','wp_cart_options_page');
add_action('init', 'widget_wp_paypal_shopping_cart_init');
//add_filter('the_content', 'print_wp_cart_button',11);

add_filter('the_content', 'print_wp_cart_button_new',11);
add_filter('the_content', 'shopping_cart_show');

add_shortcode('show_wp_shopping_cart', 'show_wp_shopping_cart_handler');

add_shortcode('always_show_wp_shopping_cart', 'always_show_cart_handler');

add_action('wp_head', 'wp_cart_css');
add_action('wp_head', 'wp_cart_add_read_form_javascript');
?>