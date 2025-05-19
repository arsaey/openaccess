@extends('voyager::master')

@section('content')
    <form action="{{  url('/admin/get-report') }}" method="POST">
        @csrf 
        <div class="form-group">
            <label for="start_datetime">Start DateTime:</label>
            <input type="date" id="start_datetime" name="start_datetime" class="form-control" required>
        </div>

        <div class="form-group">
            <label for="end_datetime">End DateTime:</label>
            <input type="date" id="end_datetime" name="end_datetime" class="form-control" required>
        </div>

        <div class="form-group">
            <label for="uid">UID:</label>
            <input type="text" id="uid" name="uid" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-primary mt-3">Submit</button>
    </form>
@endsection
