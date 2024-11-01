  
  (function ($) {
    $(function () {

        let colorInput = document.querySelector("#colorInput");
        let colorValue = colorInput.value;     

        document.querySelector("#colorInput").addEventListener('input', function () {
            
            colorValue = colorInput.value;

            if (!colorValue.startsWith("#")) {
                colorValue = "#" + colorValue;
                // colorInput.setAttribute("maxlength", 6);
            } else {
                // colorInput.setAttribute("maxlength", 7);
            }

            $("label[for='radio-custom']").css('background-color', colorValue)
            $("#radio-custom").attr("value", colorValue);

        });


        var customColor = document.querySelector("#radio-custom").value;
        $("label[for='radio-custom']").css('background-color', customColor);
    });
  }(jQuery))
