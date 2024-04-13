import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls, PanelColorSettings } from '@wordpress/block-editor';
import {
	Disabled,
	PanelBody,
	SelectControl,
	FormTokenField,
	ToggleControl,
	Flex,
	FlexBlock,
	FlexItem,
	RangeControl,
	TextControl,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';

// https://github.com/WordPress/gutenberg/issues/33576
import ServerSideRender from '@wordpress/server-side-render';

import './editor.scss';

export default function Edit( { attributes, setAttributes } ) {
	const holidaySettings = useSelect( ( select ) => {
		const site = select( 'core' ).getEntityRecord( 'root', 'site' );
		return site?.xo_event_calendar_holiday_settings;
	}, [] );
	const holidaySettingKeys = holidaySettings ? Object.keys( holidaySettings ) : [];

	if ( 0 === attributes.year ) {
		const now = new Date();
		attributes.year = now.getFullYear();
		attributes.month = now.getMonth() + 1;
	}

	const inspectorControls = (
		<InspectorControls>
			<PanelBody
				title={ __( 'Calendar settings', 'xo-event-calendar' ) }
				initialOpen={ false }
			>
				<RangeControl
					label={ __(
						'Number of months to display',
						'xo-event-calendar'
					) }
					value={ attributes.months }
					onChange={ ( value ) => setAttributes( { months: value } ) }
					isShiftStepEnabled={ true }
					shiftStep={ 12 }
					step={ 1 }
					min={ 1 }
					max={ 12 }
				/>

				<RangeControl
					label={ __(
						'Columns',
						'xo-event-calendar'
					) }
					value={ attributes.columns }
					onChange={ ( value ) => setAttributes( { columns: value } ) }
					isShiftStepEnabled={ true }
					shiftStep={ 4 }
					step={ 1 }
					min={ 1 }
					max={ 4 }
				/>

				<SelectControl
					label={ __( 'Start of week', 'xo-event-calendar' ) }
					value={ attributes.startOfWeek }
					onChange={ ( value ) =>
						setAttributes( { startOfWeek: parseInt( value ) } )
					}
					options={ [
						{
							value: -1,
							label: __(
								'General Settings',
								'xo-event-calendar'
							),
						},
						{
							value: 0,
							label: __( 'Sunday', 'xo-event-calendar' ),
						},
						{
							value: 1,
							label: __( 'Monday', 'xo-event-calendar' ),
						},
						{
							value: 2,
							label: __( 'Tuesday', 'xo-event-calendar' ),
						},
						{
							value: 3,
							label: __( 'Wednesday', 'xo-event-calendar' ),
						},
						{
							value: 4,
							label: __( 'Thursday', 'xo-event-calendar' ),
						},
						{
							value: 5,
							label: __( 'Friday', 'xo-event-calendar' ),
						},
						{
							value: 6,
							label: __( 'Saturday', 'xo-event-calendar' ),
						},
					] }
				/>

				<ToggleControl
					label={ __( 'Feed month', 'xo-event-calendar' ) }
					checked={ attributes.navigation }
					onChange={ ( value ) =>
						setAttributes( { navigation: value } )
					}
				/>

				{ attributes.navigation && (
					<Flex direction="row" align="baseline">
						<FlexBlock>
							<SelectControl
								label={ __(
									'Previous month',
									'xo-event-calendar'
								) }
								value={ attributes.prevMonths }
								onChange={ ( value ) =>
									setAttributes( {
										prevMonths: parseInt( value ),
									} )
								}
								options={ [
									{
										value: -1,
										label: __(
											'No limit',
											'xo-event-calendar'
										),
									},
									{
										value: 0,
										label: __(
											'None',
											'xo-event-calendar'
										),
									},
									{ value: 1, label: '1' },
									{ value: 2, label: '2' },
									{ value: 3, label: '3' },
									{ value: 4, label: '4' },
									{ value: 5, label: '5' },
									{ value: 6, label: '6' },
									{ value: 7, label: '7' },
									{ value: 8, label: '8' },
									{ value: 9, label: '9' },
									{ value: 10, label: '10' },
									{ value: 11, label: '11' },
									{ value: 12, label: '12' },
								] }
							/>
						</FlexBlock>

						<FlexBlock>
							<SelectControl
								label={ __(
									'Next month',
									'xo-event-calendar'
								) }
								value={ attributes.nextMonths }
								onChange={ ( value ) =>
									setAttributes( {
										nextMonths: parseInt( value ),
									} )
								}
								options={ [
									{
										value: -1,
										label: __(
											'No limit',
											'xo-event-calendar'
										),
									},
									{
										value: 0,
										label: __(
											'None',
											'xo-event-calendar'
										),
									},
									{ value: 1, label: '1' },
									{ value: 2, label: '2' },
									{ value: 3, label: '3' },
									{ value: 4, label: '4' },
									{ value: 5, label: '5' },
									{ value: 6, label: '6' },
									{ value: 7, label: '7' },
									{ value: 8, label: '8' },
									{ value: 9, label: '9' },
									{ value: 10, label: '10' },
									{ value: 11, label: '11' },
									{ value: 12, label: '12' },
								] }
							/>
						</FlexBlock>
					</Flex>
				) }

				<ToggleControl
					label={ __(
						'Specify initial display month',
						'xo-event-calendar'
					) }
					checked={ attributes.selectedMonth }
					onChange={ ( value ) =>
						setAttributes( { selectedMonth: value } )
					}
				/>

				{ attributes.selectedMonth && (
					<Flex direction="row" align="baseline">
						<FlexItem>
							<SelectControl
								value={ attributes.month }
								onChange={ ( value ) =>
									setAttributes( {
										month: parseInt( value ),
									} )
								}
								options={ [
									{
										value: 1,
										label: __(
											'January',
											'xo-event-calendar'
										),
									},
									{
										value: 2,
										label: __(
											'February',
											'xo-event-calendar'
										),
									},
									{
										value: 3,
										label: __(
											'March',
											'xo-event-calendar'
										),
									},
									{
										value: 4,
										label: __(
											'April',
											'xo-event-calendar'
										),
									},
									{
										value: 5,
										label: __( 'May', 'xo-event-calendar' ),
									},
									{
										value: 6,
										label: __(
											'June',
											'xo-event-calendar'
										),
									},
									{
										value: 7,
										label: __(
											'July',
											'xo-event-calendar'
										),
									},
									{
										value: 8,
										label: __(
											'August',
											'xo-event-calendar'
										),
									},
									{
										value: 9,
										label: __(
											'September',
											'xo-event-calendar'
										),
									},
									{
										value: 10,
										label: __(
											'October',
											'xo-event-calendar'
										),
									},
									{
										value: 11,
										label: __(
											'November',
											'xo-event-calendar'
										),
									},
									{
										value: 12,
										label: __(
											'December',
											'xo-event-calendar'
										),
									},
								] }
							/>
						</FlexItem>

						<FlexBlock>
							<NumberControl
								value={ attributes.year }
								onChange={ ( value ) =>
									setAttributes( { year: parseInt( value ) } )
								}
								isShiftStepEnabled={ true }
								shiftStep={ 10 }
								step={ 1 }
								min={ 1900 }
								max={ 3000 }
							/>
						</FlexBlock>
					</Flex>
				) }

				<ToggleControl
					label={ __( 'Default title format', 'xo-event-calendar' ) }
					checked={ attributes.defaultTitle }
					onChange={ ( value ) =>
						setAttributes( { defaultTitle: value } )
					}
				/>

				{ ! attributes.defaultTitle && (
					<TextControl
						label={ __( 'Format', 'xo-event-calendar' ) }
						value={ attributes.titleFormat }
						onChange={ ( value ) =>
							setAttributes( { titleFormat: value } )
						}
					/>
				) }

				<ToggleControl
					label={ __( 'Locale translation', 'xo-event-calendar' ) }
					checked={ attributes.localeTranslation }
					onChange={ ( value ) =>
						setAttributes( { localeTranslation: value } )
					}
				/>
			</PanelBody>

			<PanelBody
				title={ __( 'Holiday settings', 'xo-event-calendar' ) }
				initialOpen={ false }
			>
				<FormTokenField
					label={ __( 'Holidays to display', 'xo-event-calendar' ) }
					onChange={ ( value ) => {
						setAttributes( {
							holidays: value ? value.join( ' ' ) : '',
						} );
					} }
					value={
						attributes.holidays
							? attributes.holidays.split( ' ' )
							: []
					}
					suggestions={ holidaySettingKeys }
					__experimentalExpandOnFocus={ true }
				/>
			</PanelBody>

			<PanelColorSettings
				title={ __( 'Color', 'xo-event-calendar' ) }
				initialOpen={ false }
				disableCustomColors={ false }
				colorSettings={ [
					{
						label: __( 'Caption text', 'xo-event-calendar' ),
						value: attributes.captionTextColor,
						onChange: ( value ) => { setAttributes( { captionTextColor: value } ) }
					},
					{
						label: __( 'Caption background', 'xo-event-calendar' ),
						value: attributes.captionBackgroundColor,
						onChange: ( value ) => { setAttributes( { captionBackgroundColor: value } ) }
					},
				] }
			>
			</PanelColorSettings>
		</InspectorControls>
	);

	return (
		<div { ...useBlockProps() }>
			{ inspectorControls }
			<Disabled>
				<ServerSideRender
					block="xo-event-calendar/simple-calendar"
					attributes={ { ...attributes } }
				/>
			</Disabled>
		</div>
	);
}
