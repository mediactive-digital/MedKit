@extends('medKitTheme::_layouts.back.app')

@section('content')
<ol class="breadcrumb">
	<li class="breadcrumb-item">
		<a href="{!! route('back.mailTemplates.index') !!}">{!! _i('Mail Template') !!}</a>
	</li>
	<li class="breadcrumb-item active">{!! _i('Détail') !!}</li>
</ol>
<div class="container-fluid">
	<div class="animated fadeIn">

		<div class="row">
			<div class="col-lg-12">
				<div class="card">
					<div class="card-header">
						<strong>{!! _i('Détails') !!}</strong>
						<a href="{!! route('back.mailTemplates.index') !!}" class="btn btn-light">
							<i class="material-icons">arrow_back</i>
							{!! _i('Retour') !!}
						</a>
					</div>
					<div class="card-body">
						@include('mail_templates.show_fields')
					</div>
				</div>
			</div>

			<div class="col-lg-12">
				<div class="card">
					<div class="card-header">
						<strong>{!! _i('Variables du template') !!}</strong>
						<a href="{!! route('back.mailTemplates.index') !!}" class="btn btn-light">
							<i class="material-icons">arrow_back</i>
							{!! _i('Retour') !!}
						</a>
					</div>
					<div class="card-body">
						<!-- Text Template Field -->
						<div class="row one-ui-kit-example-container">
							<div class="col-12">
								<ul class="list-group">
									<?php
									foreach ($templateVars as $varName) {
										?>
										<li class="list-group-item d-flex justify-content-between align-items-center">
											<?php echo $varName; ?>
											<!--<span class="badge badge-primary badge-pill">14</span>-->
										</li>
										<?php
									}
									?>
								</ul>
							</div>
						</div>


					</div>
				</div>
			</div>



		</div>


	</div>
</div>
@endsection
