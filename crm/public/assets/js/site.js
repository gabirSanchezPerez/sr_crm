function convertedRow(id, name) {
  Swal.fire({
    icon: "info",
    html:"Para convertir el Cliente Potencial ( " +name +" ) a Anunciante, debes haber registrado su RFC previamente.",
    showConfirmButton: true,
    timer: 4000,
    timerProgressBar: true,
  });
}

if ($(".hora").length > 0) {
    $(".hora").datetimepicker({
      datepicker: false,
      format: "H:i",
      //mask:true,
      step: 15,
    });
  }
  if ($(".fecha").length > 0) {
    $(".fecha").datetimepicker({
      i18n: {
        en: {
          months: ["Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre",],
          dayOfWeekShort: ["Dom", "Lun", "Mar", "Mie", "Jue", "Vie", "Sab"],
        },
      },
      timepicker: false,
      format: "Y-m-d",
      //mask:true,
    });
  }