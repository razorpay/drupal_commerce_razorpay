// function rzpAjaxCall(data) {
//     var body = {
//         mode: 'test',
//         key: "0c08FC07b3eF5C47Fc19B6544afF4A98",
//         events: [
//             {
//                 event_type: 'plugin-events',
//                 event_version: 'v1',
//                 timestamp: new Date().getTime(),
//                 event: data['event'],
//                 properties: data
//             },
//         ]
//     };
//     console.log(jQuery.ajax({
//         url: "https://lumberjack.razorpay.com/v1/track",
//         method: "POST",
//         headers: {
//             "Content-Type": "application/json"
//         },
//         data: JSON.stringify(body),
//         dataType: "json",
//         success: function(response) {
//             console.log(response);
//         },
//         error: function() {
//             console.log("fail");
//         }
//     }));
    
//     jQuery.ajax({
//         url: "https://lumberjack.razorpay.com/v1/track",
//         method: "POST",
//         headers: {
//             "Content-Type": "application/json"
//         },
//         data: JSON.stringify(body),
//         dataType: "json",
//         success: function(response) {
//             console.log(response);
//         },
//         error: function() {
//             console.log("fail");
//         }
//     });

//     // Drupal.ajax({
//     //     url: 'https://lumberjack.razorpay.com/v1/track',
//     //     type : 'POST',
//     //     data : toString(body),
//     //     success : function (response) {console.log(response);},
//     //     error : function() { console.log("failed"); }
//     // }).execute();

//     // jQuery.ajax({
//     //     url: "https://lumberjack.razorpay.com/v1/track",
//     //     method: "POST",
//     //     body : toString(body),
//     //     success: function(response) {
//     //         console.log(response);
//     //     },
//     //     error: function() {
//     //         console.log("fail");
//     //     }
//     //   });
// }

// function rzpSignupClicked()
// {
//     var data = {
//         'plugin_name': 'drupal',
// 		'action' : 'rzpInstrumentation',
// 		'event' : 'signup.initiated',
// 		'next_page_url' : 'https://easy.razorpay.com/onboarding/?recommended_product=payment_gateway&source=drupal'
// 	};

//     rzpAjaxCall(data);
// }

Drupal.behaviors.myBehavior = {
    attach: function (context, settings) {
        console.log("hi");
        var data = {
            'plugin_name': 'drupal',
            'action' : 'rzpInstrumentation',
            'event' : 'signup.initiated',
            'next_page_url' : 'https://easy.razorpay.com/onboarding/?recommended_product=payment_gateway&source=drupal'
        };

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

        Drupal.ajax({
            url: "https://lumberjack.razorpay.com/v1/track",
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            data: JSON.stringify(body),
            success: function(response) {
                console.log(response);
            },
            error: function() {
                console.log("fail");
            }
        });
    }
  };
