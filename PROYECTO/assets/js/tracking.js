function rainbowHide() {
    $(".mmo-illustration--rainbow svg").attr("class", "hide");
  }
  function rainbowFadeOutDown() {
    $(".mmo-illustration--rainbow svg").attr("class", "animated fadeOutDown");
  }
  function rainbowFadeInUp() {
    $(".mmo-illustration--rainbow svg").attr("class", "animated fadeInUp");
  }
  
  // Clicking on the timeline truck stops
  var rainbowActivation = false;
  
  $( "#stop-01" ).click( function() {
    $(".mmo-modal--tracking-intro").addClass( "mmo-state-01" ).removeClass( "mmo-state-02 mmo-state-03" );
    if (rainbowActivation == true) {
      rainbowFadeOutDown();
    }
    else {
      rainbowHide();
    }
  });
  $( "#stop-02" ).click( function() {
    $(".mmo-modal--tracking-intro").addClass( "mmo-state-02" ).removeClass( "mmo-state-01 mmo-state-03" );
    if (rainbowActivation == true) {
      rainbowFadeOutDown();
    }
  });
  $( "#stop-03" ).click( function() {
    $(".mmo-modal--tracking-intro").addClass( "mmo-state-03" ).removeClass( "mmo-state-01 mmo-state-02" );
    rainbowFadeInUp();
    rainbowActivation = true;
  });
  
  // Left & right button navigation
  $( "#btn-left" ).click( function() {
  
    if ( $( ".mmo-modal--tracking-intro" ).hasClass( "mmo-state-02" ) ) {
      $(".mmo-modal--tracking-intro").addClass( "mmo-state-01" ).removeClass( "mmo-state-02" );
      rainbowHide();
    }
    else if ( $( ".mmo-modal--tracking-intro" ).hasClass( "mmo-state-03" ) ) {
      $(".mmo-modal--tracking-intro").addClass( "mmo-state-02" ).removeClass( "mmo-state-03" );
      rainbowFadeOutDown();
    }
   
  });
  
  $( "#btn-right" ).click( function() {
    
    if ( $( ".mmo-modal--tracking-intro" ).hasClass( "mmo-state-01" ) ) {
      $(".mmo-modal--tracking-intro").addClass( "mmo-state-02" ).removeClass( "mmo-state-01" );
      rainbowHide();
    }
    else if ( $( ".mmo-modal--tracking-intro" ).hasClass( "mmo-state-02" ) ) {
      $(".mmo-modal--tracking-intro").addClass( "mmo-state-03" ).removeClass( "mmo-state-02" );
      rainbowFadeInUp();
    }
   
  });
  
  // Keyboard arrow navigation
  $( window ).keydown(function (e) {
    
    // Left arrow
    if ( ( (e.keyCode == 37) && $( ".mmo-modal--tracking-intro" ).hasClass( "mmo-state-02" ) ) ) {
      $(".mmo-modal--tracking-intro").addClass( "mmo-state-01" ).removeClass( "mmo-state-02" );
      rainbowHide();
    }
    else if ( ( (e.keyCode == 37) && $( ".mmo-modal--tracking-intro" ).hasClass( "mmo-state-03" ) ) ) {
      $(".mmo-modal--tracking-intro").addClass( "mmo-state-02" ).removeClass( "mmo-state-03" );
      rainbowFadeOutDown();
    }
    
    // Right arrow
    else if ( ( (e.keyCode == 39) && $( ".mmo-modal--tracking-intro" ).hasClass( "mmo-state-01" ) ) ) {
      $(".mmo-modal--tracking-intro").addClass( "mmo-state-02" ).removeClass( "mmo-state-01" );
      rainbowHide();
    }
    else if ( ( (e.keyCode == 39) && $( ".mmo-modal--tracking-intro" ).hasClass( "mmo-state-02" ) ) ) {
      $(".mmo-modal--tracking-intro").addClass( "mmo-state-03" ).removeClass( "mmo-state-02" );
      rainbowFadeInUp();
    }
    
  });
  
  
  
  
  