document.addEventListener("focusout", function (event) {
    if(event.target.matches("#drupal_razorpay_key_id")) {
        var data = {
            'plugin_name': 'drupal',
			'event' : 'formfield.interacted',
            'page_url' : window.location.href,
            'field_type' : 'string',
            'field_name' : 'drupal_razorpay_key_id'
		};
        rzpFetch(data);
    }

    if(event.target.matches("#drupal_razorpay_key_secret")) {
        var data = {
            'plugin_name': 'drupal',
			'event' : 'formfield.interacted',
            'page_url' : window.location.href,
            'field_type' : 'string',
            'field_name' : 'drupal_razorpay_key_secret'
		};
        rzpFetch(data);
    }
})
 
function rzpFetch(data) {
    var body = {
        mode: 'test',
        key: "2Ea4C263F7bb3f3AF7630DC5db9e38ff",
        context: {},
        events: [
            {
                mode: 'test',
                event_type: 'plugin-events',
                event_version: 'v1',
                timestamp: new Date().getTime(),
                event: data['event'],
                properties: data
            },
        ]
    };

    fetch('https://lumberjack.stage.razorpay.in/v1/track', {method: 'post',
                                                                  body: JSON.stringify(body),
                                                                  headers: {
                                                                  },
                                                                });
}

function rzpSignupClicked()
{
    var data = {
        'plugin_name': 'drupal',
		'event' : 'signup.initiated',
		'next_page_url' : 'https://easy.razorpay.com/onboarding/?recommended_product=payment_gateway&source=drupal'
	};

    rzpFetch(data);
}

function rzpLoginClicked()
{
    var data = {
        'plugin_name': 'drupal',
		'action' : 'rzpInstrumentation',
		'event' : 'login.initiated',
		'next_page_url' : 'https://dashboard.razorpay.com/signin?screen=sign_in&source=drupal'
	};

    rzpFetch(data);
}
