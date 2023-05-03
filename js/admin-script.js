function rzpAjaxCall(data) {
    var body = {
        mode: 'test',
        key: "0c08FC07b3eF5C47Fc19B6544afF4A98",
        events: [
            {
                event_type: 'plugin-events',
                event_version: 'v1',
                timestamp: new Date().getTime(),
                event: data['event'],
                properties: data
            },
        ]
    };
    // console.log(JSON.stringify(body));
    
    // Drupal.ajax({
    //     url: 'https://lumberjack.razorpay.com/v1/track',
    //     type : 'POST',
    //     data : body,
    //     success : function (response) {console.log(response);},
    //     error : function() { console.log("failed"); }
    // }).execute();

    jQuery.ajax({
        url: "https://lumberjack.razorpay.com/v1/track",
        method: "POST",
        headers: {
            "Content-Type": "application/json"
          },
        body : JSON.stringify(body),
        success: function(response) {
            console.log(response);
        },
        error: function() {
            console.log("fail");
        }
      });
}

function rzpSignupClicked()
{
    var data = {
        'plugin_name': 'drupal',
		'action' : 'rzpInstrumentation',
		'event' : 'signup.initiated',
		'next_page_url' : 'https://easy.razorpay.com/onboarding/?recommended_product=payment_gateway&source=drupal'
	};

    rzpAjaxCall(data);
}