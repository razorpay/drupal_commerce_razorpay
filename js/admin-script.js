document.addEventListener("focusout", function (event) 
{
    if(event.target.matches("#drupal_razorpay_key_id") || event.target.matches("#drupal_razorpay_key_secret")) 
    {
        var data = {
            'plugin_name': 'drupal',
            'event' : 'formfield.interacted',
            'page_url' : window.location.href,
            'field_type' : 'text',
            'field_name' : event.target.id
        };

        rzpFetch(data);
    }
})

function rzpFetch(data) 
{
    let text = document.querySelector('#drupal_razorpay_key_id').value;

    var mode = text.substr(0, 8) === "rzp_test" ? 'test' : 'live';

    var body = {
        mode: mode,
        key: "0c08FC07b3eF5C47Fc19B6544afF4A98",
        context: {},
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

    fetch('https://lumberjack.stage.razorpay.in/v1/track', 
        {
            method: 'post',
            body: JSON.stringify(body),
            headers: {
            },
        }
    );
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
        'event' : 'login.initiated',
        'next_page_url' : 'https://dashboard.razorpay.com/signin?screen=sign_in&source=drupal'
    };

    rzpFetch(data);
}
