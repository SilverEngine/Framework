{{ extends('layouts.auth') }}

#set[content]
    <div class="col-md-4">
        <form action="{{ route('auth_login') }}" method="post">
            <label for="email">email:</label>
            <input type="text"  class="form-control" name="email" placeholder="email">
            <br>
            <label for="password">Password:</label>
            <input type="password" class="form-control" name="password" placeholder="Passowrd">
            <br>
            <input type="submit" value="login" class="btn btn-primary pull-right">
        </form>
    </div>
#end
