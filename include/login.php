<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
session_start();


?>

<div class="login-box">
  <div class="card">
    <div class="card-body login-card">
      <div class="login-logo">
        <span class="mark"><i class="fa fa-wifi"></i></span>
        <span class="login-title">MIKHMON</span>
        <span class="muted"><?= esc($_please_login) ?></span>
      </div>
      <?= $error; ?>
      <form autocomplete="off" action="" method="post"><?= csrf_field() ?>
        <div class="field">
          <label for="_username">Username</label>
          <input class="form-control" type="text" name="user" id="_username" placeholder="Username" required="1" autofocus autocomplete="username">
        </div>
        <div class="field">
          <label for="_password">Password</label>
          <input class="form-control" type="password" name="pass" id="_password" placeholder="Password" required="1" autocomplete="current-password">
        </div>
        <input class="btn-login bg-primary pointer" type="submit" name="login" value="Login">
      </form>
    </div>
  </div>
</div>

</body>
</html>
