<div class="fxh-process-timeline">
    @foreach($steps as $step)
        <div class="fxh-process-step"><span>{{ $loop->iteration }}</span><div><strong>{{ $step['title'] }}</strong><p>{{ $step['text'] }}</p></div></div>
    @endforeach
</div>
