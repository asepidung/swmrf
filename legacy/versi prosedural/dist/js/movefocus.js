function moveFocusToNextInput(event, currentInput, inputName) {
   if (event.key === "Enter") {
      var inputs = document.getElementsByName(inputName);
      var currentIndex = Array.from(inputs).indexOf(currentInput);

      if (currentIndex >= 0 && currentIndex < inputs.length - 1) {
         var nextInput = inputs[currentIndex + 1];
         nextInput.focus();
      }
   }
}
