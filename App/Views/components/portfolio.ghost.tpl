<section class="content-section" id="portfolio">
  <div class="container">
    <div class="content-section-heading text-center">
      <h3 class="text-secondary mb-0">Portfolio</h3>
      <h2 class="mb-5">Recent Projects</h2>
    </div>
    <div class="row no-gutters">
      #foreach($component_payload as $data)
        <div class="col-lg-6">
          <a class="portfolio-item" href="#">
            <span class="caption">
              <span class="caption-content">
                <h2>{{ $data['title'] }}</h2>
                <p class="mb-0">{{ $data['description'] }}</p>
              </span>
            </span>
            <img class="img-fluid" src="{{ asset('img/portfolio-') }}{{ $data['id'] }}.jpg" alt="">
          </a>
        </div>
      #endforeach
    </div>
  </div>
</section>
