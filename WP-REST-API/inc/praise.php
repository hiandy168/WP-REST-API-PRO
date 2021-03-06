<?php
/*
 * 
 * WordPres 连接微信小程序
 * Author: jianbo + 艾码汇
 * github:  https://github.com/dchijack/WP-REST-API-PRO
 * 基于 守望轩 WP REST API For App 开源插件定制
 * 
 */
// 定义文章赞赏 API
add_action( 'rest_api_init', function () {
	register_rest_route( 'wechat/v1', 'praise/post', array(
		'methods' => 'POST',
		'callback' => 'postPraise'
	));
});
function postPraise($request) {
    $openid= $request['openid'];       
    $orderid=$request['orderid'];
    $postid=$request['postid'];
    $money=$request['money'];
    if (empty($openid) || empty($orderid) || empty($money) || empty($postid)) {
		return new WP_Error( 'error', 'openid or postid or money or orderid is empty', array( 'status' => 500 ) );
    } else if (get_post($postid)==null) {
        return new WP_Error( 'error', 'post id is error', array( 'status' => 500 ) );
    } else { 
        if(!username_exists($openid)) {
			return new WP_Error( 'error', 'Not allowed to submit', array('status' => 500 ));
        }  else if (is_wp_error(get_post($postid))) {
            return new WP_Error( 'error', 'post id is error', array( 'status' => 500 ) );
        } else {
            $data=post_praise_data($openid,$postid,$orderid,$money); 
            if (empty($data)) {
                return new WP_Error( 'error', 'post praise error', array( 'status' => 404 ) );
            }
            $response = new WP_REST_Response($data);
            $response->set_status( 200 ); 
            return $response;
        }
    }
}
function post_praise_data($openid,$postid,$orderid,$money){
    $openid="_".$openid;
    $orderid=$orderid;
    $meta_key=$openid."@"."$orderid";
    $meta_value=$money."_praise";
    if(update_post_meta($postid,$meta_key,$meta_value,true)) {
        $result["code"]="success";
        $result["message"]="post praise success";
        $result["status"]="200";    
        return $result;
    } else {
        $result["code"]="success";
        $result["message"]="post praise error";
        $result["status"]="500";                   
        return $result;
    } 
}
// 定义用户赞赏 API
add_action( 'rest_api_init', function () {
	register_rest_route( 'wechat/v1', 'praise/user', array(
		'methods' => 'GET',
		'callback' => 'getmypraise'
	));
});
function getmypraise($request) {
    $openid= $request['openid'];   
    if (empty($openid)) {
        return new WP_Error( 'error', 'openid is empty', array( 'status' => 500 ) );
    } else { 
        if(!username_exists($openid)) {
            return new WP_Error( 'error', 'Not allowed to submit', array( 'status' => 500 ) );
        } else {
            $data=post_mypraise_data($openid); 
            if (empty($data)) {
                return new WP_Error( 'error', 'get my praise error', array( 'status' => 404 ) );
            }
            $response = new WP_REST_Response($data);
            $response->set_status( 200 ); 
            return $response;
        }
    }
}
function post_mypraise_data($openid) {
    global $wpdb;
	$sql = $wpdb->prepare("SELECT * from ".$wpdb->posts." where post_type='post' and ID in (SELECT post_id from ".$wpdb->postmeta." where meta_value like '%praise' and meta_key like'%".$openid."%') ORDER BY post_date desc LIMIT 20");
    $_posts = $wpdb->get_results($sql);
    $posts =array();
    foreach ($_posts as $post) {
        $_data["id"] = $post->ID;
        $_data["title"]["rendered"] = $post->post_title;
        $posts[]=$_data;
    }
    $result["code"]="success";
    $result["message"]="get my praise success";
    $result["status"]="200";
    $result["data"]=$posts;                   
     return $result;         
}
// 定义所有赞赏 API
add_action( 'rest_api_init', function () {
	register_rest_route( 'wechat/v1', 'praise/all', array(
		'methods' => 'GET',
		'callback' => 'getallpraise'
	));
});
function getallpraise($request) {     
    $data=post_allpraise_data(); 
    if (empty($data)) {
        return new WP_Error( 'error', 'get all praise error', array( 'status' => 404 ) );
    }
    $response = new WP_REST_Response($data);
    $response->set_status( 200 ); 
    return $response;
}
function post_allpraise_data() {
    global $wpdb;
	$sql= $wpdb->prepare("SELECT ".$wpdb->users.".display_name as avatarurl from(SELECT substring(substring_index(".$wpdb->postmeta.".meta_key,'@',1),2) as openid,".$wpdb->postmeta.".meta_id from ".$wpdb->postmeta." where ".$wpdb->postmeta.".meta_value like '%praise' )t1  LEFT JOIN ".$wpdb->users." ON ".$wpdb->users.".user_login = t1.openid  ORDER by t1.meta_id desc"); // 修复 SQL 注入漏洞
    $avatarurls = $wpdb->get_results($sql);
    if (!empty($avatarurls)) {
        $result["code"]="success";
        $result["message"]="get all praise success";
        $result["status"]="200";
        $result["avatarurls"]=$avatarurls;   
    } else {
        $result["code"]="success";
        $result["message"]="post all praise error";
        $result["status"]="500";                   
        return $result;
    }                     
    return $result;         
}  