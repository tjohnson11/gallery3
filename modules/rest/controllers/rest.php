<?php defined("SYSPATH") or die("No direct script access.");
/**
 * Gallery - a web based photo album viewer and editor
 * Copyright (C) 2000-2009 Bharat Mediratta
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or (at
 * your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street - Fifth Floor, Boston, MA  02110-1301, USA.
 */
class Rest_Controller extends Controller {
  public function index() {
    try {
      $username = Input::instance()->post("user");
      $password = Input::instance()->post("password");

      $user = identity::lookup_user_by_name($username);
      if (empty($user) || !identity::is_correct_password($user, $password)) {
        throw new Rest_Exception("Forbidden", 403);
      }

      $key = ORM::factory("user_access_token")
        ->where("user_id", "=", $user->id)
        ->find();
      if (!$key->loaded()) {
        $key->user_id = $user->id;
        $key->access_key = md5($user->name . rand());
        $key->save();
      }

      rest::reply($key->access_key);
    } catch (Exception $e) {
      rest::send_headers($e);
    }
 }

  public function __call($function, $args) {
    $input = Input::instance();
    switch ($method = strtolower($input->server("REQUEST_METHOD"))) {
    case "get":
      $request->params = (object) Input::instance()->get();
      break;

    case "post":
      $request->params = (object) Input::instance()->post();
      if (isset($_FILES["file"])) {
        $request->file = upload::save("file");
      }
      break;
    }

    $request->method = strtolower($input->server("HTTP_X_GALLERY_REQUEST_METHOD", $method));
    $request->access_token = $input->server("HTTP_X_GALLERY_REQUEST_KEY");
    $request->url = url::abs_current(true);

    try {
      rest::set_active_user($request->access_token);

      $handler_class = "{$function}_rest";
      $handler_method = $request->method;

      if (!method_exists($handler_class, $handler_method)) {
        throw new Rest_Exception("Forbidden", 403);
      }

      print call_user_func(array($handler_class, $handler_method), $request);
    } catch (Rest_Exception $e) {
      rest::send_headers($e);
    }
  }
}