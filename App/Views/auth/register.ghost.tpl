{{ extends('layouts.auth') }}

#set[content]
<div class="col-md-4">
    <form action="{{ route('auth_register') }}" method="post">
        <label for="Username">Username:</label>
        <input type="text"  class="form-control" name="Username" placeholder="Username">
        <br>
        <label for="email">email:</label>
        <input type="text"  class="form-control" name="email" placeholder="email">
        <br>
        <label for="password">Password:</label>
        <input type="password" class="form-control" name="password" placeholder="Passowrd">
        <br>
        <label for="password">Password:</label>
        <input type="password" class="form-control" name="password_again" placeholder="Passowrd again">
        <br>
        <input type="submit" value="login" class="btn btn-primary pull-right">
    </form>
</div>
#end
